<?php

namespace App\Modules\Auth\Tests\Security;

use App\Modules\Auth\Controllers\MemberController;
use App\Modules\Auth\Controllers\SecurityTestsController;
use App\Modules\Auth\Controllers\SessionController;
use App\Modules\Auth\Models\Application;
use App\Modules\Auth\Models\Applications;
use App\Modules\Auth\Models\Confirmation;
use App\Modules\Auth\Models\Confirmations;
use App\Modules\Auth\Models\Devices;
use App\Modules\Auth\Models\Member;
use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use Colibri\App;
use Colibri\Data\Storages\Models\DataRow;
use Colibri\Encryption\Crypt;
use Colibri\Utils\Cache\Mem;
use App\Modules\Auth\Ldap\Directory;
use RuntimeException;

/**
 * Exercises the deployed Auth HTTP boundary using isolated fixture identities.
 * @class
 * @memberof App\Modules\Auth\Tests\Security
 * @extends Object
 */
class Regression
{
    /**
     * HTTPS origin selected by the CLI host.
     * @var string
     * @private
     * @ignore
     */
    private string $_origin;

    /**
     * Secret-free test outcomes.
     * @var array
     * @private
     * @ignore
     */
    private array $_checks = [];

    /**
     * Unique namespace for this run's fixture identities.
     * @var string
     * @private
     * @ignore
     */
    private string $_runId;

    /**
     * Disposable application, unrelated to configured production applications.
     * @var Application|null
     * @private
     * @ignore
     */
    private ?Application $_application = null;

    /**
     * Exact fixture logins scheduled for cleanup before creation.
     * @var string[]
     * @private
     * @ignore
     */
    private array $_logins = [];

    /**
     * Session keys observed in fixture HTTP responses.
     * @var array<string, bool>
     * @private
     * @ignore
     */
    private array $_sessionKeys = [];

    /**
     * Exact contact-confirmation rows not owned by registered accounts yet.
     * @var int[]
     * @private
     * @ignore
     */
    private array $_confirmationIds = [];

    /**
     * Initializes a suite without accepting arbitrary request destinations.
     * @public
     * @param string $origin Configured HTTPS origin.
     * @return void
     */
    public function __construct(string $origin)
    {
        if (!preg_match('~\Ahttps://[a-zA-Z0-9][a-zA-Z0-9._-]*\z~D', $origin)) {
            throw new RuntimeException('Invalid security test origin');
        }
        $this->_origin = $origin;
        $this->_runId = 'security-test-' . bin2hex(random_bytes(12));
    }

    /**
     * Runs bounded checks without using existing user credentials.
     * @public
     * @return array Machine-readable report.
     */
    public function Run(): array
    {
        try {
            $this->_application = Applications::LoadEmpty();
            $this->_application->key = $this->_runId;
            $this->_application->token = bin2hex(random_bytes(16));
            $this->_application->params = [
                'askforemail' => true,
                'askforphone' => true,
                'livetime' => 3600,
                'multilogin' => true,
                'defaultrole' => 'user',
                'roles' => [
                    ['name' => 'administrator', 'desc' => 'Fixture administrator'],
                    ['name' => 'user', 'desc' => 'Fixture user']
                ],
                'domains' => [['pattern' => parse_url($this->_origin, PHP_URL_HOST)]],
                'enable_device_authentification' => false,
                'enable_two_factor_authentication' => true,
                'enable_two_factor_authentification_applications' => true
            ];
            $this->_save($this->_application);
            $headers = [
                'X-AppName: ' . $this->_application->key,
                'X-AppToken: ' . $this->_application->token
            ];
            $password = 'Aa9!-' . bin2hex(random_bytes(20));
            $alice = $this->_member('alice', $password);
            $bob = $this->_member('bob', $password);
            $this->_roles($alice, $password, $headers);
            if (getenv('AUTH_SECURITY_TESTS_ROLES_ONLY') !== '1') {
                $this->_passwordHashing($password, $headers);
                $this->_boundaries($headers);
                $this->_registration($password, $headers);
                $this->_verifiedRegistration($password, $headers);
                $this->_sessions($alice, $bob, $password, $headers);
                $this->_administration($alice, $bob, $password, $headers);
                $this->_device($bob, $headers);
                $this->_reset($alice, $password, $headers);
            }
        } finally {
            $this->_cleanup();
        }
        return [
            'origin' => $this->_origin,
            'run_id' => $this->_runId,
            'cleanup' => 'complete',
            'passed' => count(array_filter($this->_checks, static fn(array $check): bool => $check['passed'])),
            'failed' => count(array_filter($this->_checks, static fn(array $check): bool => !$check['passed'])),
            'checks' => $this->_checks
        ];
    }

    /**
     * Verifies role persistence, filtering, priority and LDAP membership.
     * @private
     * @ignore
     * @param Member $member Disposable fixture member.
     * @param string $password Fixture password.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _roles(Member $member, string $password, array $headers): void
    {
        $this->_check('legacy-role-assignment-becomes-array', $member->role->ToArray(true) === [['role' => 'user']]);
        $member->role = [['role' => 'user'], ['role' => 'administrator'], ['role' => 'user']];
        $this->_save($member);
        $fresh = Members::LoadById($member->id);
        $expected = [['role' => 'user'], ['role' => 'administrator']];
        $this->_check('multiple-roles-survive-save-and-export', $fresh->ToArray(true)['role'] === $expected);
        $this->_check('administrator-role-is-detected-in-any-position', $fresh->HasRole('administrator'));
        $found = false;
        foreach (Members::LoadByRole('administrator') as $match) {
            if ($match->token === $member->token) {
                $found = true;
            }
        }
        $this->_check('role-filter-matches-array-membership', $found);
        $this->_check('empty-role-filter-matches-nothing', Members::LoadByRole([])->Count() === 0);
        $rejected = false;
        try {
            $fresh->role = [['role' => 'user'], ['role' => 'unknown-fixture-role']];
        } catch (\InvalidArgumentException) {
            $rejected = true;
        }
        $this->_check('role-outside-application-lists-is-rejected', $rejected
            && Members::LoadById($member->id)->GetRoleNames() === ['user', 'administrator']);

        $application = Applications::LoadById($this->_application->id);
        $application->params->roles = [
            ['name' => 'administrator', 'desc' => 'Fixture administrator', 'priority' => 1],
            ['name' => 'user', 'desc' => 'Fixture user', 'priority' => 10]
        ];
        $this->_save($application);
        $this->_check('priority-uses-most-privileged-assigned-role', $fresh->GetApplicationRole($application)->name === 'administrator');
        $directory = new Directory($application->key, $application->token);
        $base = $directory->GetBaseDn();
        $dn = 'uid=' . $member->token . ',ou=users,' . $base;
        $membership = [];
        foreach ($directory->Entries() as $entry) {
            if ($entry['dn'] === $dn) {
                $membership = $entry['attributes']['memberof'];
            }
        }
        $this->_check('ldap-exposes-all-configured-role-groups', $membership === [
            'cn=user,ou=groups,' . $base,
            'cn=administrator,ou=groups,' . $base
        ]);
        try {
            $member->role = ['administrator'];
            $this->_check('primitive-role-arrays-are-rejected', false);
        } catch (\InvalidArgumentException $exception) {
            $this->_check('primitive-role-arrays-are-rejected', true);
        }
        $member = Members::LoadById($member->id);
        $member->role = [];
        $this->_save($member);
        $this->_check('empty-roles-remove-current-role', !$member->HasRole('administrator'));
        $this->_check('empty-roles-persist', Members::LoadById($member->id)->GetRoleNames() === []);
        $this->_check('empty-roles-remove-ldap-access', !$directory->IsAuthorized($member->token));
        $member = Members::LoadById($member->id);
        $member->role = 'user';
        $this->_save($member);
        $member = Members::LoadById($member->id);
        $member->role = $expected;
        $this->_save($member);
        $login = $this->_login($member, $password, $headers);
        $adminHeaders = array_merge($headers, ['Authorization: ' . $login['data']['session']['token']]);
        $response = $this->_request(MemberController::GetEntryPoint('ChangeRole', 'json'), [
            'email' => $member->email,
            'role' => [['role' => 'administrator'], ['role' => 'user']]
        ], $adminHeaders);
        $this->_check('administrator-can-change-multiple-roles', $response['status'] === 200
            && Members::LoadById($member->id)->GetRoleNames() === ['administrator', 'user']);
        $response = $this->_request(MemberController::GetEntryPoint('ChangeRole', 'json'), [
            'email' => $member->email,
            'role' => [['role' => 'unknown-fixture-role']]
        ], $adminHeaders);
        $this->_check('unknown-role-change-is-rejected-without-saving', $response['status'] !== 200
            && Members::LoadById($member->id)->GetRoleNames() === ['administrator', 'user']);
        $member = Members::LoadById($member->id);
        $member->role = 'user';
        $this->_save($member);
    }

    /**
     * Verifies gradual password migration without changing rejected logins.
     * @private
     * @ignore
     * @param string $password Strong fixture password.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _passwordHashing(string $password, array $headers): void
    {
        $member = $this->_member('password-hashing', $password);
        $fresh = Members::LoadById($member->id);
        $this->_check('new-password-uses-argon2id',
            password_get_info($fresh->password)['algoName'] === 'argon2id' && password_verify($password, $fresh->password)
        );

        $legacyPassword = 'short';
        $legacyHash = md5(Crypt::Encrypt(Member::PasswordKey, $legacyPassword));
        $member->SetData(array_replace($member->ToArray(), ['members_password' => $legacyHash]));
        $member->two_factor_application = 'JBSWY3DPEHPK3PXP';
        $this->_save($member);
        $this->_check('legacy-password-verification-is-read-only',
            $member->Authorize($legacyPassword) && $member->password === $legacyHash
        );
        $response = $this->_request(SessionController::GetEntryPoint('Login', 'json'), [
            'login' => $member->email, 'password' => $password
        ], $headers);
        $this->_denied('wrong-legacy-password-rejected', $response);
        $fresh = Members::LoadById($member->id);
        $this->_check('wrong-password-does-not-upgrade-hash', $fresh->password === $legacyHash);

        $response = $this->_request(SessionController::GetEntryPoint('Login', 'json'), [
            'login' => $member->email, 'password' => $legacyPassword
        ], $headers);
        $fresh = Members::LoadById($member->id);
        $this->_check('pending-second-factor-does-not-upgrade-hash',
            $response['status'] === 206 && $fresh->password === $legacyHash
        );
        $response = $this->_request(SessionController::GetEntryPoint('Login', 'json'), [
            'login' => $member->email, 'password' => $legacyPassword, 'code' => 'invalid'
        ], $headers);
        $this->_denied('invalid-second-factor-rejected', $response);
        $fresh = Members::LoadById($member->id);
        $this->_check('invalid-second-factor-does-not-upgrade-hash', $fresh->password === $legacyHash);

        $fresh->two_factor_application = null;
        $this->_save($fresh);
        $this->_login($fresh, $legacyPassword, $headers);
        $fresh = Members::LoadById($member->id);
        $this->_check('successful-login-upgrades-weak-legacy-password',
            password_get_info($fresh->password)['algoName'] === 'argon2id'
            && password_verify($legacyPassword, $fresh->password)
        );
        $upgradedHash = $fresh->password;
        $this->_login($fresh, $legacyPassword, $headers);
        $fresh = Members::LoadById($member->id);
        $this->_check('current-password-hash-is-not-rewritten', $fresh->password === $upgradedHash);
        $this->_check('upgraded-password-rejects-wrong-password', !$fresh->Authorize($password));
        $fresh->password = $upgradedHash;
        $this->_save($fresh);
        $fresh = Members::LoadById($member->id);
        $this->_check('assigned-password-hash-is-stored-as-is', $fresh->password === $upgradedHash
            && $fresh->Authorize($legacyPassword));
        $fresh->password = $password;
        $this->_save($fresh);
        $fresh = Members::LoadById($member->id);
        $this->_check('assigned-plaintext-password-is-hashed', $fresh->password !== $password
            && password_get_info($fresh->password)['algoName'] === 'argon2id' && $fresh->Authorize($password));
    }

    /**
     * Preserves verified registration while rejecting another guest's proof.
     * @private
     * @ignore
     * @param string $password Random fixture password.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _verifiedRegistration(string $password, array $headers): void
    {
        $email = $this->_runId . '-verified@example.invalid';
        $phone = '+997' . random_int(1000000000, 9999999999);
        $this->_logins[] = $email;
        $guest = $this->_request(SessionController::GetEntryPoint('Start', 'json'), [], $headers);
        $this->_requireSuccess($guest);
        $ownerHeaders = array_merge($headers, ['Authorization: ' . $guest['data']['session']['token']]);
        $records = [];
        foreach (['email' => $email, 'phone' => $phone] as $property => $value) {
            $record = Confirmations::LoadEmpty();
            $record->property = $property;
            $record->value = $value;
            $record->member = md5($guest['data']['session']['token']);
            $record->code = (string)random_int(100000, 999999);
            $record->verified = false;
            $this->_save($record);
            $this->_confirmationIds[] = $record->id;
            $payload = ['property' => $property, 'value' => $value, 'code' => $record->code];
            $response = $this->_request(MemberController::GetEntryPoint('ConfirmProperty', 'json'), $payload, $headers);
            $this->_denied('another-guest-cannot-confirm-' . $property, $response);
            $response = $this->_request(MemberController::GetEntryPoint('ConfirmProperty', 'json'), $payload, $ownerHeaders);
            $this->_requireSuccess($response);
            $records[] = $record;
        }
        $foreignEmail = $this->_runId . '-foreign-proof@example.invalid';
        $this->_logins[] = $foreignEmail;
        $record = Confirmations::LoadEmpty();
        $record->property = Confirmation::PropertyEmail;
        $record->value = $foreignEmail;
        $record->member = md5($guest['data']['session']['token']);
        $record->code = (string)random_int(100000, 999999);
        $record->verified = true;
        $this->_save($record);
        $this->_confirmationIds[] = $record->id;
        $response = $this->_request(MemberController::GetEntryPoint('Register', 'json'), [
            'email' => $foreignEmail,
            'phone' => '+997' . random_int(1000000000, 9999999999),
            'password' => $password,
            'confirmation' => $password,
            'email_confirmed' => true
        ], $headers);
        $this->_requireSuccess($response);
        $foreignMember = Members::LoadByEmail($foreignEmail);
        $this->_check('registration-cannot-borrow-another-guest-proof', !$foreignMember->email_confirmed);
        $response = $this->_request(MemberController::GetEntryPoint('Register', 'json'), [
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'confirmation' => $password,
            'email_confirmed' => false,
            'phone_confirmed' => false
        ], $ownerHeaders);
        $this->_requireSuccess($response);
        $member = Members::LoadByEmail($email);
        $this->_check('verified-registration-preserves-contact-proof', $member->email_confirmed && $member->phone_confirmed && $member->GetRoleNames() === ['user']);
        $this->_check('registration-consumes-contact-proofs',
            Confirmations::LoadById($records[0]->id) === null && Confirmations::LoadById($records[1]->id) === null
        );
    }

    /**
     * Preserves legitimate administration and revokes all target sessions.
     * @private
     * @ignore
     * @param Member $alice Temporary administrator.
     * @param Member $bob Target disposable account.
     * @param string $password Fixture password.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _administration(Member $alice, Member $bob, string $password, array $headers): void
    {
        $alice->role = 'administrator';
        $this->_save($alice);
        try {
            $login = $this->_login($alice, $password, $headers);
            $adminHeaders = array_merge($headers, ['Authorization: ' . $login['data']['session']['token']]);
            $bobLogin = $this->_login($bob, $password, $headers);
            $response = $this->_request(MemberController::GetEntryPoint('ForceLogout', 'json'), ['token' => $bob->token], $adminHeaders);
            $after = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], array_merge($headers, [
                'Authorization: ' . $bobLogin['data']['session']['token']
            ]));
            $this->_check('administrator-can-revoke-member-session', $response['status'] === 200 && empty($after['data']['session']['member']));
            $first = $this->_login($bob, $password, $headers);
            $second = $this->_login($bob, $password, $headers);
            $response = $this->_request(MemberController::GetEntryPoint('ForceBlock', 'json'), ['token' => $bob->token], $adminHeaders);
            $fresh = Members::LoadById($bob->id);
            $this->_check('administrator-can-block-account', $response['status'] === 200 && $fresh->blocked);
            foreach ([$first, $second] as $index => $session) {
                $after = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], array_merge($headers, [
                    'Authorization: ' . $session['data']['session']['token']
                ]));
                $this->_check('blocking-revokes-session-' . ($index + 1), empty($after['data']['session']['member']));
            }
        } finally {
            $alice->role = 'user';
            $this->_save($alice);
            $bob->blocked = false;
            $this->_save($bob);
        }
    }

    /**
     * Checks application credentials and the CLI-only test entry point.
     * @private
     * @ignore
     * @param array $headers Valid fixture application headers.
     * @return void
     */
    private function _boundaries(array $headers): void
    {
        foreach ([
            SessionController::GetEntryPoint('Start', 'json'),
            MemberController::GetEntryPoint('List', 'json')
        ] as $path) {
            $this->_denied('missing-application: ' . $path, $this->_request($path));
            $this->_denied('invalid-application: ' . $path, $this->_request($path, [], [
                $headers[0], 'X-AppToken: ' . bin2hex(random_bytes(16))
            ]));
        }
        $this->_denied('security-runner-is-not-http-accessible', $this->_request(
            SecurityTestsController::GetEntryPoint('Run', 'json'), [], $headers
        ));
        $guest = $this->_request(SessionController::GetEntryPoint('Start', 'json'), [], $headers);
        $this->_requireSuccess($guest);
        $this->_check('guest-session-is-anonymous', empty($guest['data']['session']['member']));
        $this->_denied('guest-cannot-edit-profile', $this->_request(
            MemberController::GetEntryPoint('UpdateProfile', 'json'),
            ['first_name' => 'Unauthorized', 'last_name' => 'Fixture'],
            $headers
        ));
    }

    /**
     * Detects client-controlled privileges and unverified contact assertions.
     * @private
     * @ignore
     * @param string $password Random fixture password.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _registration(string $password, array $headers): void
    {
        $email = $this->_runId . '-registration@example.invalid';
        $this->_logins[] = $email;
        $response = $this->_request(MemberController::GetEntryPoint('Register', 'json'), [
            'email' => $email,
            'phone' => '+997' . random_int(1000000000, 9999999999),
            'password' => $password,
            'confirmation' => $password,
            'role' => 'administrator',
            'email_confirmed' => true,
            'phone_confirmed' => true
        ], $headers);
        $member = Members::LoadByEmail($email);
        $this->_check('registration-cannot-select-administrator', !$member || !$member->HasRole('administrator'), $response['status']);
        $this->_check('registration-cannot-self-confirm-email', !$member || !$member->email_confirmed, $response['status']);
        $this->_check('registration-cannot-self-confirm-phone', !$member || !$member->phone_confirmed, $response['status']);
        if (!$member && $response['status'] === 200) {
            throw new RuntimeException('Registration returned success without a fixture account');
        }
        if (!in_array($response['status'], [200, 400, 401, 403, 422], true)) {
            throw new RuntimeException('Registration did not reach the expected endpoint');
        }
    }

    /**
     * Exercises credential rejection, session replay and cross-account controls.
     * @private
     * @ignore
     * @param Member $alice First disposable account.
     * @param Member $bob Second disposable account.
     * @param string $password Random fixture password.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _sessions(Member $alice, Member $bob, string $password, array $headers): void
    {
        $this->_denied('wrong-password-rejected', $this->_request(
            SessionController::GetEntryPoint('Login', 'json'),
            ['login' => $alice->email, 'password' => 'Wrong9!-' . bin2hex(random_bytes(12))],
            $headers
        ));
        $login = $this->_login($alice, $password, $headers);
        $token = $login['data']['session']['token'];
        $sessionHeaders = array_merge($headers, ['Authorization: ' . $token]);
        $decoded = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], $sessionHeaders);
        $this->_requireSuccess($decoded);
        $this->_check('valid-session-restores-own-member', ($decoded['data']['session']['member']['token'] ?? null) === $alice->token);
        $cookieSession = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], array_merge($headers, ['Cookie: cc-jwt=' . $token]));
        $this->_requireSuccess($cookieSession);
        $this->_check('httponly-cookie-restores-own-member', ($cookieSession['data']['session']['member']['token'] ?? null) === $alice->token);
        $this->_check('session-does-not-expose-password-or-secret',
            !isset($decoded['data']['session']['secret']) && !isset($decoded['data']['session']['member']['password'])
        );
        $this->_check('session-cookie-is-secure', $this->_cookieFlag($login['headers'], 'secure'));
        $this->_check('session-cookie-is-httponly', $this->_cookieFlag($login['headers'], 'httponly'));
        $this->_check('session-cookie-preserves-cross-site-policy', $this->_cookieFlag($login['headers'], 'samesite=none'));
        $forged = substr($token, 0, -1) . (str_ends_with($token, 'A') ? 'B' : 'A');
        $response = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], array_merge($headers, ['Authorization: ' . $forged]));
        $this->_check('tampered-token-cannot-authenticate', empty($response['data']['session']['member']), $response['status']);
        $this->_requireSuccess($this->_request(SessionController::GetEntryPoint('Logout', 'json'), [], $sessionHeaders));
        $response = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], $sessionHeaders);
        $this->_check('logout-revokes-old-token', empty($response['data']['session']['member']), $response['status']);
        $aliceLogin = $this->_login($alice, $password, $headers);
        $bobLogin = $this->_login($bob, $password, $headers);
        $aliceHeaders = array_merge($headers, ['Authorization: ' . $aliceLogin['data']['session']['token']]);
        $bobHeaders = array_merge($headers, ['Authorization: ' . $bobLogin['data']['session']['token']]);
        $response = $this->_request(MemberController::GetEntryPoint('ForceLogout', 'json'), ['token' => $bob->token], $aliceHeaders);
        $after = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], $bobHeaders);
        $this->_check('user-cannot-logout-another-account',
            in_array($response['status'], [400, 401, 403], true) && ($after['data']['session']['member']['token'] ?? null) === $bob->token,
            $response['status']
        );
        $response = $this->_request(MemberController::GetEntryPoint('ForceBlock', 'json'), ['token' => $bob->token], $aliceHeaders);
        $freshBob = Members::LoadById($bob->id);
        $this->_check('user-cannot-block-another-account', !$freshBob->blocked && in_array($response['status'], [400, 401, 403], true), $response['status']);
        $freshBob->blocked = false;
        $this->_save($freshBob);
        $expired = Sessions::LoadEmpty();
        $expired->member = $alice->token;
        $expired->expires = 1;
        $this->_save($expired);
        $this->_sessionKeys[$expired->key] = true;
        sleep(2);
        $response = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], array_merge($headers, ['Authorization: ' . $expired->token]));
        $this->_check('expired-session-cannot-authenticate', empty($response['data']['session']['member']), $response['status']);
    }

    /**
     * Requires device authentication to prove possession and honor account blocking.
     * @private
     * @ignore
     * @param Member $bob Disposable account.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _device(Member $bob, array $headers): void
    {
        $device = Devices::LoadEmpty();
        $device->token = $bob->token;
        $device->device = $this->_runId;
        $device->rawid = bin2hex(random_bytes(24));
        $this->_save($device);
        $payload = ['credentials' => ['deviceId' => 'not-the-registered-device', 'rawId' => $device->rawid]];
        $response = $this->_request(SessionController::GetEntryPoint('LoginByCreds', 'json'), $payload, $headers);
        $this->_check('device-id-without-proof-cannot-login', empty($response['data']['session']['member']), $response['status']);
        $bob->blocked = true;
        $this->_save($bob);
        try {
            $response = $this->_request(SessionController::GetEntryPoint('LoginByCreds', 'json'), $payload, $headers);
            $this->_check('blocked-account-cannot-use-device-login', empty($response['data']['session']['member']), $response['status']);
        } finally {
            $bob->blocked = false;
            $this->_save($bob);
        }
    }

    /**
     * Verifies reset authorization, single use and revocation without sending mail.
     * @private
     * @ignore
     * @param Member $alice Disposable account.
     * @param string $password Initial password.
     * @param array $headers Fixture application headers.
     * @return void
     */
    private function _reset(Member $alice, string $password, array $headers): void
    {
        $before = $this->_login($alice, $password, $headers);
        $confirmation = Confirmations::LoadEmpty();
        $confirmation->member = $alice->token;
        $confirmation->property = Confirmation::PropertyReset;
        $confirmation->code = (string)random_int(100000, 999999);
        $confirmation->verified = false;
        $this->_save($confirmation);
        $newPassword = 'Bb8!-' . bin2hex(random_bytes(20));
        $payload = [
            'email' => $alice->email,
            'phone' => $alice->phone,
            'password' => $newPassword,
            'confirmation' => $newPassword,
            'code' => $confirmation->code === '000000' ? '000001' : '000000'
        ];
        $response = $this->_request(MemberController::GetEntryPoint('ResetPassword', 'json'), $payload, $headers);
        $fresh = Members::LoadById($alice->id);
        $this->_check('wrong-reset-code-does-not-change-password', $fresh->Authorize($password) && !$fresh->Authorize($newPassword), $response['status']);
        $payload['code'] = $confirmation->code;
        $response = $this->_request(MemberController::GetEntryPoint('ResetPassword', 'json'), $payload, $headers);
        $this->_requireSuccess($response);
        $fresh = Members::LoadById($alice->id);
        $this->_check('valid-reset-code-changes-password',
            $fresh->Authorize($newPassword) && !$fresh->Authorize($password)
            && password_get_info($fresh->password)['algoName'] === 'argon2id'
        );
        $response = $this->_request(SessionController::GetEntryPoint('Decode', 'json'), [], array_merge($headers, [
            'Authorization: ' . $before['data']['session']['token']
        ]));
        $this->_check('password-reset-revokes-existing-session', empty($response['data']['session']['member']), $response['status']);
        $replayPassword = 'Cc7!-' . bin2hex(random_bytes(20));
        $payload['password'] = $replayPassword;
        $payload['confirmation'] = $replayPassword;
        $response = $this->_request(MemberController::GetEntryPoint('ResetPassword', 'json'), $payload, $headers);
        $fresh = Members::LoadById($alice->id);
        $this->_check('reset-code-is-single-use', $fresh->Authorize($newPassword) && !$fresh->Authorize($replayPassword), $response['status']);
        $this->_check('used-reset-code-reports-rejection', in_array($response['status'], [400, 401, 403, 422], true), $response['status']);
    }

    /**
     * Creates an account without communications or existing identity dependencies.
     * @private
     * @ignore
     * @param string $name Fixture suffix.
     * @param string $password Random fixture password.
     * @return Member Saved fixture member.
     */
    private function _member(string $name, string $password): Member
    {
        $email = $this->_runId . '-' . $name . '@example.invalid';
        $this->_logins[] = $email;
        $member = Members::Register($email, $email, '+997' . random_int(1000000000, 9999999999), $password);
        $member->first_name = 'Security';
        $member->last_name = 'Fixture';
        $member->role = 'user';
        $member->email_confirmed = false;
        $member->phone_confirmed = false;
        $member->blocked = false;
        $member->two_factor = false;
        $this->_save($member);
        return $member;
    }

    /**
     * Requires the positive login control before testing authorization failures.
     * @private
     * @ignore
     * @param Member $member Fixture identity.
     * @param string $password Fixture password.
     * @param array $headers Fixture application headers.
     * @return array Successful login response.
     */
    private function _login(Member $member, string $password, array $headers): array
    {
        $response = $this->_request(SessionController::GetEntryPoint('Login', 'json'), [
            'login' => $member->email, 'password' => $password
        ], $headers);
        $this->_requireSuccess($response);
        if (($response['data']['session']['member']['token'] ?? null) !== $member->token || empty($response['data']['session']['token'])) {
            throw new RuntimeException('Positive login control failed');
        }
        return $response;
    }

    /**
     * Checks a flag only on the authentication cookie, not unrelated cookies.
     * @private
     * @ignore
     * @param array $headers Response header lines.
     * @param string $flag Required cookie attribute.
     * @return bool Whether every cc-jwt cookie has the required attribute.
     */
    private function _cookieFlag(array $headers, string $flag): bool
    {
        $cookies = array_filter($headers, static fn(string $line): bool => str_starts_with(strtolower($line), 'set-cookie: cc-jwt='));
        $pattern = '/;\s*' . preg_quote($flag, '/') . '\s*(?:;|$)/i';
        return count($cookies) > 0 && count(array_filter($cookies, static fn(string $line): bool => preg_match($pattern, $line) === 1)) === count($cookies);
    }

    /**
     * Persists fixture state through the generated model API.
     * @private
     * @ignore
     * @param DataRow $row Fixture record.
     * @return void
     */
    private function _save(DataRow $row): void
    {
        if ($row->Save(true) !== true) {
            throw new RuntimeException('Cannot persist security fixture');
        }
    }

    /**
     * Deletes only exact run identities, their dependent rows and named key files.
     * @private
     * @ignore
     * @return void
     */
    private function _cleanup(): void
    {
        foreach ($this->_confirmationIds as $id) {
            $record = Confirmations::LoadById($id);
            if ($record) {
                $this->_delete($record);
            }
        }
        foreach ($this->_logins as $login) {
            $member = Members::LoadByLogin($login);
            if (!$member) {
                continue;
            }
            foreach ([Sessions::class, Confirmations::class, Devices::class] as $table) {
                $field = $table === Devices::class ? 'token' : 'member';
                $rows = $table::LoadByFilter(-1, 100, '{' . $field . '}=[[owner:string]]', null, ['owner' => $member->token], false);
                if ($rows === null) {
                    throw new RuntimeException('Cannot load dependent security fixtures for cleanup');
                }
                foreach ($rows as $row) {
                    if ($row instanceof \App\Modules\Auth\Models\Session) {
                        $this->_sessionKeys[$row->key] = true;
                    }
                    $this->_delete($row);
                }
            }
            $runtime = App::$appRoot . App::$config->Query('runtime')->GetValue() . 'ssl/';
            $directory = $runtime . substr($member->token, 0, 4) . '/' . substr($member->token, -4);
            foreach (['', '.pub', '.local.pub'] as $suffix) {
                $path = $directory . '/' . $member->token . $suffix;
                if (is_file($path) && !unlink($path)) {
                    throw new RuntimeException('Cannot remove fixture key file');
                }
            }
            if (is_dir($directory) && count(scandir($directory)) === 2 && !rmdir($directory)) {
                throw new RuntimeException('Cannot remove empty fixture key directory');
            }
            $parent = dirname($directory);
            if (is_dir($parent) && count(scandir($parent)) === 2 && !rmdir($parent)) {
                throw new RuntimeException('Cannot remove empty fixture key parent');
            }
            $this->_delete($member);
        }
        foreach (array_keys($this->_sessionKeys) as $key) {
            Mem::Delete('sess' . $key);
            if (Mem::Exists('sess' . $key)) {
                throw new RuntimeException('Fixture session remains in cache after cleanup');
            }
        }
        if ($this->_application && $this->_application->id) {
            $this->_delete($this->_application);
        }
        foreach ($this->_logins as $login) {
            if (Members::LoadByLogin($login)) {
                throw new RuntimeException('Fixture account remains after cleanup');
            }
        }
        if ($this->_application && Applications::LoadByKey($this->_runId)) {
            throw new RuntimeException('Fixture application remains after cleanup');
        }
    }

    /**
     * Rejects cleanup errors instead of reporting a successful run.
     * @private
     * @ignore
     * @param DataRow $row Exact fixture record.
     * @return void
     */
    private function _delete(DataRow $row): void
    {
        $result = $row->Delete();
        if ($result !== true && (!is_object($result) || $result->error)) {
            throw new RuntimeException('Cannot delete security fixture');
        }
    }

    /**
     * Rejects a broken positive control instead of passing negative assertions.
     * @private
     * @ignore
     * @param array $response HTTP response.
     * @return void
     */
    private function _requireSuccess(array $response): void
    {
        if ($response['status'] !== 200 || !isset($response['data']['session'])) {
            throw new RuntimeException('Positive HTTP control failed');
        }
    }

    /**
     * Sends one request with TLS verification, no redirects and no shared cookies.
     * @private
     * @ignore
     * @param string $path Controller entry point.
     * @param array $payload JSON request fields.
     * @param array $headers Authentication headers.
     * @return array HTTP status, parsed JSON and response headers.
     */
    private function _request(string $path, array $payload = [], array $headers = []): array
    {
        $handle = curl_init($this->_origin . $path);
        if ($handle === false) {
            throw new RuntimeException('Cannot initialize HTTP transport');
        }
        try {
            $hasSession = count(array_filter($headers, static fn(string $header): bool =>
                str_starts_with(strtolower($header), 'authorization:') || str_starts_with(strtolower($header), 'cookie: cc-jwt=')
            )) > 0;
            $hasApplication = count(array_filter($headers, static fn(string $header): bool => str_starts_with(strtolower($header), 'x-appname:'))) > 0;
            if ($hasApplication && !$hasSession) {
                $guest = Sessions::CreateGuestSession();
                $this->_sessionKeys[$guest->key] = true;
                $headers[] = 'Authorization: ' . $guest->token;
            }
            $responseHeaders = [];
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode((object)$payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_HEADERFUNCTION => static function (\CurlHandle $curl, string $line) use (&$responseHeaders): int {
                    $responseHeaders[] = trim($line);
                    return strlen($line);
                }
            ]);
            $body = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($body === false || $status === 0) {
                throw new RuntimeException('Security test HTTP transport failed');
            }
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException('Security test endpoint did not return JSON data');
            }
            if ($status >= 500 || $status < 200 || ($status >= 300 && $status < 400)) {
                throw new RuntimeException('Security test endpoint returned an unexpected HTTP status');
            }
            if (isset($data['result']) && is_array($data['result'])) {
                $data = $data['result'];
            }
            if (!empty($data['session']['token'])) {
                $this->_sessionKeys[md5($data['session']['token'])] = true;
            }
            return ['status' => $status, 'data' => $data, 'headers' => $responseHeaders];
        } finally {
            curl_close($handle);
        }
    }

    /**
     * Requires an explicit authorization denial rather than an arbitrary error.
     * @private
     * @ignore
     * @param string $name Test identifier.
     * @param array $response HTTP response.
     * @return void
     */
    private function _denied(string $name, array $response): void
    {
        $this->_check($name, in_array($response['status'], [400, 401, 403], true), $response['status']);
    }

    /**
     * Records only assertion names, outcomes and non-secret diagnostic metadata.
     * @private
     * @ignore
     * @param string $name Test identifier.
     * @param bool $passed Assertion result.
     * @param int|null $status Observed HTTP status.
     * @return void
     */
    private function _check(string $name, bool $passed, ?int $status = null): void
    {
        $this->_checks[] = ['name' => $name, 'passed' => $passed, 'http_status' => $status];
    }
}
