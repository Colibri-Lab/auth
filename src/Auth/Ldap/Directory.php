<?php

namespace App\Modules\Auth\Ldap;

use App\Modules\Auth\Models\Application;
use App\Modules\Auth\Models\Applications;
use App\Modules\Auth\Models\Member;
use App\Modules\Auth\Models\Members;
use InvalidArgumentException;
use OverflowException;

/**
 * Exposes Auth members and application roles as a read-only LDAP directory.
 *
 * @class
 * @memberof App\Modules\Auth\Ldap
 * @extends Object
 */
class Directory
{
    /**
     * Selected application key.
     * @var string
     * @private
     * @ignore
     */
    private string $_name;

    /**
     * Selected application token, never exposed in LDAP attributes.
     * @var string
     * @private
     * @ignore
     */
    private string $_token;

    /**
     * Validates the application before accepting connections.
     * @public
     * @param string $name Application key and directory domain label.
     * @param string $token Application token.
     */
    public function __construct(string $name, string $token)
    {
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,62}$/D', $name) || $token === '') {
            throw new InvalidArgumentException('Invalid LDAP application parameters');
        }
        $this->_name = $name;
        $this->_token = $token;
        $this->_application();
    }

    /**
     * Returns the naming context for this application.
     * @public
     * @return string Directory base DN.
     */
    public function GetBaseDn(): string
    {
        return 'dc=' . strtolower($this->_name);
    }

    /**
     * Authenticates a login, email, phone or canonical user DN.
     * @public
     * @param string $identity Presented identity.
     * @param string $password Presented password.
     * @return string|null Member token, or null on rejection.
     */
    public function Authenticate(string $identity, string $password): ?string
    {
        if ($identity === '' || $password === '') {
            return null;
        }
        $member = null;
        if (preg_match('/^uid=([a-f0-9]{32}),ou=users,' . preg_quote($this->GetBaseDn(), '/') . '$/iD', $identity, $match)) {
            $member = Members::LoadByToken(strtolower($match[1]));
        } elseif (!str_contains($identity, '=')) {
            $member = Members::LoadByLogin($identity) ?? Members::LoadByEmail($identity) ?? Members::LoadByPhone($identity);
        }
        if ($member === null || !$this->_eligible($member, $this->_application()) || !$member->Authorize($password)) {
            return null;
        }
        $member->UpgradePasswordHash($password);
        return $member->token;
    }

    /**
     * Rechecks account state and application eligibility on every search.
     * @public
     * @param string $token Authenticated member token.
     * @return bool Whether access remains valid.
     */
    public function IsAuthorized(string $token): bool
    {
        $member = Members::LoadByToken($token);
        return $member !== null && $this->_eligible($member, $this->_application());
    }

    /**
     * Streams bounded directory entries without copying password data.
     * @public
     * @return iterable<array{dn: string, attributes: array}> Directory entries.
     */
    public function Entries(): iterable
    {
        $application = $this->_application();
        $base = $this->GetBaseDn();
        yield ['dn' => $base, 'attributes' => ['objectclass' => ['top', 'domain'], 'dc' => [$this->_name]]];
        foreach (['users', 'groups'] as $unit) {
            yield ['dn' => 'ou=' . $unit . ',' . $base, 'attributes' => ['objectclass' => ['top', 'organizationalUnit'], 'ou' => [$unit]]];
        }
        $groups = [];
        foreach ($application->params->roles ?? [] as $role) {
            $name = (string)$role->name;
            if ($name !== '') {
                $groups[$name] = ['description' => (string)$role->desc, 'members' => []];
            }
        }
        for ($page = 1; $page <= 101; $page++) {
            $members = Members::LoadByFilter($page, 100, '{blocked}=[[blocked:integer]]', '{id} asc', ['blocked' => 0], false);
            if ($members === null || $members->Count() === 0) {
                break;
            }
            if ($page === 101) {
                throw new OverflowException('LDAP directory scan limit exceeded');
            }
            foreach ($members as $member) {
                if (!$this->_eligible($member, $application)) {
                    continue;
                }
                $dn = 'uid=' . $member->token . ',ou=users,' . $base;
                $groupDns = [];
                foreach ($member->GetRoleNames() as $name) {
                    if (isset($groups[$name])) {
                        $groupDns[] = 'cn=' . self::EscapeDn($name) . ',ou=groups,' . $base;
                        $groups[$name]['members'][] = $dn;
                    }
                }
                $displayName = trim($member->first_name . ' ' . $member->last_name);
                if ($displayName === '') {
                    $displayName = $member->login ?: $member->email;
                }
                $attributes = [
                    'objectclass' => ['top', 'person', 'organizationalPerson', 'inetOrgPerson'],
                    'uid' => array_values(array_unique(array_filter([$member->login, $member->token]))),
                    'entryuuid' => [substr($member->token, 0, 8) . '-' . substr($member->token, 8, 4) . '-' . substr($member->token, 12, 4) . '-' . substr($member->token, 16, 4) . '-' . substr($member->token, 20)],
                    'cn' => [$displayName],
                    'displayname' => [$displayName],
                    'sn' => [$member->last_name ?: $displayName],
                    'givenname' => [(string)$member->first_name],
                    'mail' => [(string)$member->email],
                    'telephonenumber' => [(string)$member->phone],
                    'memberof' => $groupDns,
                ];
                yield ['dn' => $dn, 'attributes' => array_filter($attributes, static fn(array $values): bool => $values !== [''])];
            }
            if ($members->Count() < 100) {
                break;
            }
        }
        foreach ($groups as $name => $group) {
            $attributes = [
                'objectclass' => ['top', 'groupOfNames'],
                'cn' => [(string)$name],
                'description' => [$group['description']],
                'member' => $group['members'],
            ];
            if ($attributes['member'] === []) {
                $attributes['member'] = ['cn=empty,' . $base];
            }
            yield ['dn' => 'cn=' . self::EscapeDn((string)$name) . ',ou=groups,' . $base, 'attributes' => $attributes];
        }
    }

    /**
     * Escapes an RDN value without requiring the PHP LDAP extension.
     * @public
     * @static
     * @param string $value Raw RDN value.
     * @return string RFC 4514 escaped value.
     */
    public static function EscapeDn(string $value): string
    {
        $escaped = '';
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if (str_contains(',+"\\<>;=', $byte) || ord($byte) < 32 || ($index === 0 && ($byte === ' ' || $byte === '#')) || ($index === $length - 1 && $byte === ' ')) {
                $escaped .= '\\' . bin2hex($byte);
            } else {
                $escaped .= $byte;
            }
        }
        return $escaped;
    }

    /**
     * Reloads application configuration so revocations take effect immediately.
     * @private
     * @ignore
     * @return Application Selected application.
     */
    private function _application(): Application
    {
        $application = Applications::LoadByToken($this->_token);
        if ($application === null || $application->key !== $this->_name) {
            throw new InvalidArgumentException('LDAP application not found');
        }
        return $application;
    }

    /**
     * Restricts LDAP to active, single-factor members of configured roles.
     * @private
     * @ignore
     * @param Member $member Candidate member.
     * @param Application $application Selected application.
     * @return bool Whether this member may use LDAP.
     */
    private function _eligible(Member $member, Application $application): bool
    {
        return !$member->blocked && !$member->two_factor && !$member->two_factor_application
            && preg_match('/^[a-f0-9]{32}$/D', (string)$member->token) === 1
            && $member->GetApplicationRole($application) !== null;
    }
}