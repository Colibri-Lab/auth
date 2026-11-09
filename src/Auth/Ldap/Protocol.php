<?php

namespace App\Modules\Auth\Ldap;

use InvalidArgumentException;
use OverflowException;
use Throwable;

/**
 * Handles one LDAPv3 connection with an isolated authentication state.
 *
 * @class
 * @memberof App\Modules\Auth\Ldap
 * @extends Object
 */
class Protocol
{
    /**
     * Directory belonging to the configured application.
     * @var Directory
     * @private
     * @ignore
     */
    private Directory $_directory;

    /**
     * Successfully authenticated member token.
     * @var string|null
     * @private
     * @ignore
     */
    private ?string $_identity = null;

    /**
     * Creates an anonymous LDAP connection.
     * @public
     * @param Directory $directory Application directory.
     */
    public function __construct(Directory $directory)
    {
        $this->_directory = $directory;
    }

    /**
     * Processes a complete BER message, returning null when it must close.
     * @public
     * @param string $frame Complete LDAPMessage.
     * @return string|null Encoded replies or null for Unbind.
     */
    public function Handle(string $frame): ?string
    {
        $messageId = 0;
        $responseTag = 120;
        try {
            $message = Ber::Read($frame, 48)['value'];
            if ($frame !== '') {
                throw new InvalidArgumentException('Trailing LDAP data');
            }
            $messageId = Ber::Number(Ber::Read($message, 2)['value']);
            if ($messageId === 0) {
                throw new InvalidArgumentException('Invalid LDAP message ID');
            }
            $operation = Ber::Read($message);
            $responseTag = [96 => 97, 99 => 101, 102 => 103, 104 => 105, 74 => 107, 108 => 109, 110 => 111, 119 => 120][$operation['tag']] ?? 120;
            if ($operation['tag'] === 96) {
                $this->_identity = null;
            }
            if ($operation['tag'] === 66) {
                return null;
            }
            if ($message !== '' && $this->_hasCriticalControl($message)) {
                return $this->_result($messageId, $responseTag, 12, 'Unsupported critical control');
            }
            if ($operation['tag'] === 96) {
                return $this->_bind($messageId, $operation['value']);
            }
            if ($operation['tag'] === 99) {
                return $this->_search($messageId, $operation['value']);
            }
            if ($operation['tag'] === 80) {
                Ber::Number($operation['value']);
                return '';
            }
            return $this->_result($messageId, $responseTag, 53, 'Read-only LDAP service; operation unsupported');
        } catch (InvalidArgumentException $exception) {
            return $this->_result($messageId, $responseTag, 2, 'Malformed or unsupported LDAP request');
        } catch (OverflowException $exception) {
            return $this->_result($messageId, $responseTag, 11, 'Directory administration limit exceeded');
        } catch (Throwable $exception) {
            error_log('Auth LDAP operation failed: ' . get_class($exception));
            return $this->_result($messageId, $responseTag, 80, 'Directory temporarily unavailable');
        }
    }

    /**
     * Validates LDAPv3 simple authentication, never accepting empty passwords.
     * @private
     * @ignore
     * @param int $messageId Request message ID.
     * @param string $request Bind contents.
     * @return string Bind response.
     */
    private function _bind(int $messageId, string $request): string
    {
        $version = Ber::Number(Ber::Read($request, 2)['value']);
        $name = Ber::Read($request, 4)['value'];
        $authentication = Ber::Read($request);
        if ($request !== '' || $version !== 3) {
            return $this->_result($messageId, 97, 2, 'LDAPv3 required');
        }
        if ($authentication['tag'] !== 128) {
            return $this->_result($messageId, 97, 7, 'Only simple Bind is supported');
        }
        if ($name === '' && $authentication['value'] === '') {
            return $this->_result($messageId, 97, 0);
        }
        $this->_identity = $this->_directory->Authenticate($name, $authentication['value']);
        if ($this->_identity === null) {
            return $this->_result($messageId, 97, 49, 'Invalid credentials');
        }
        return $this->_result($messageId, 97, 0);
    }

    /**
     * Executes a bounded base, one-level or subtree search.
     * @private
     * @ignore
     * @param int $messageId Request message ID.
     * @param string $request Search contents.
     * @return string Entries followed by SearchResultDone.
     */
    private function _search(int $messageId, string $request): string
    {
        $base = Ber::Read($request, 4)['value'];
        $scope = Ber::Number(Ber::Read($request, 10)['value']);
        $aliases = Ber::Number(Ber::Read($request, 10)['value']);
        $sizeLimit = Ber::Number(Ber::Read($request, 2)['value']);
        $timeLimit = Ber::Number(Ber::Read($request, 2)['value']);
        $typesOnly = Ber::Read($request, 1)['value'];
        if ($scope > 2 || $aliases > 3 || strlen($typesOnly) !== 1) {
            throw new InvalidArgumentException('Invalid search options');
        }
        $filter = $this->_filter(Ber::Read($request), 0);
        $selection = Ber::Read($request, 48)['value'];
        $attributes = [];
        while ($selection !== '') {
            $attributes[] = strtolower(Ber::Read($selection, 4)['value']);
        }
        if ($request !== '') {
            throw new InvalidArgumentException('Trailing search data');
        }
        if ($base === '' && $scope === 0) {
            $root = [
                'objectclass' => ['top'],
                'namingcontexts' => [$this->_directory->GetBaseDn()],
                'supportedldapversion' => ['3'],
                'vendorname' => ['Colibri Auth'],
            ];
            $response = '';
            if ($filter($root)) {
                $response = $this->_entry($messageId, '', $root, $attributes, ord($typesOnly) !== 0);
            }
            return $response . $this->_result($messageId, 101, 0);
        }
        if ($this->_identity === null || !$this->_directory->IsAuthorized($this->_identity)) {
            $this->_identity = null;
            return $this->_result($messageId, 101, 50, 'Authenticated Bind required');
        }
        $context = strtolower($this->_directory->GetBaseDn());
        $normalizedBase = strtolower($base);
        if ($normalizedBase !== $context && !str_ends_with($normalizedBase, ',' . $context)) {
            return $this->_result($messageId, 101, 32, 'No such object');
        }
        $deadline = microtime(true) + min($timeLimit ?: 5, 5);
        $limit = min($sizeLimit ?: 500, 500);
        $count = 0;
        $foundBase = false;
        $response = '';
        foreach ($this->_directory->Entries() as $entry) {
            if (microtime(true) >= $deadline) {
                return $response . $this->_result($messageId, 101, 3, 'Search time limit exceeded');
            }
            $dn = strtolower($entry['dn']);
            $foundBase = $foundBase || $dn === $normalizedBase;
            if (!$this->_withinScope($dn, $normalizedBase, $scope) || !$filter($entry['attributes'])) {
                continue;
            }
            if ($count >= $limit) {
                return $response . $this->_result($messageId, 101, 4, 'Search size limit exceeded');
            }
            $encoded = $this->_entry($messageId, $entry['dn'], $entry['attributes'], $attributes, ord($typesOnly) !== 0);
            if (strlen($response) + strlen($encoded) > 2097152) {
                return $response . $this->_result($messageId, 101, 11, 'Search response limit exceeded');
            }
            $response .= $encoded;
            $count++;
        }
        return $response . $this->_result($messageId, 101, $foundBase ? 0 : 32);
    }

    /**
     * Compiles bounded boolean, equality, presence and substring filters.
     * @private
     * @ignore
     * @param array $element Decoded filter.
     * @param int $depth Current recursion depth.
     * @return \Closure Predicate over normalized entry attributes.
     */
    private function _filter(array $element, int $depth): \Closure
    {
        if ($depth > 20) {
            throw new InvalidArgumentException('Filter nesting limit exceeded');
        }
        $tag = $element['tag'];
        $contents = $element['value'];
        if ($tag === 160 || $tag === 161 || $tag === 162) {
            $filters = [];
            while ($contents !== '') {
                $filters[] = $this->_filter(Ber::Read($contents), $depth + 1);
                if (count($filters) > 100) {
                    throw new InvalidArgumentException('Too many filter clauses');
                }
            }
            if ($filters === [] || ($tag === 162 && count($filters) !== 1)) {
                throw new InvalidArgumentException('Invalid boolean filter');
            }
            return static function (array $attributes) use ($filters, $tag): bool {
                foreach ($filters as $filter) {
                    $matched = $filter($attributes);
                    if ($tag === 162) {
                        return !$matched;
                    }
                    if ($tag === 160 && !$matched) {
                        return false;
                    }
                    if ($tag === 161 && $matched) {
                        return true;
                    }
                }
                return $tag === 160;
            };
        }
        if ($tag === 135) {
            $attribute = strtolower($contents);
            return static fn(array $attributes): bool => !empty($attributes[$attribute]);
        }
        if ($tag !== 163 && $tag !== 164) {
            throw new InvalidArgumentException('Unsupported matching filter');
        }
        $attribute = strtolower(Ber::Read($contents, 4)['value']);
        if ($tag === 163) {
            $value = Ber::Read($contents, 4)['value'];
            if ($contents !== '') {
                throw new InvalidArgumentException('Trailing equality data');
            }
            return static function (array $attributes) use ($attribute, $value): bool {
                foreach ($attributes[$attribute] ?? [] as $candidate) {
                    if (strcasecmp($candidate, $value) === 0) {
                        return true;
                    }
                }
                return false;
            };
        }
        $parts = Ber::Read($contents, 48)['value'];
        if ($contents !== '' || $parts === '') {
            throw new InvalidArgumentException('Invalid substring filter');
        }
        $pattern = '';
        $position = 0;
        $hasFinal = false;
        while ($parts !== '') {
            $part = Ber::Read($parts);
            if (!in_array($part['tag'], [128, 129, 130], true) || ($part['tag'] === 128 && $position !== 0) || $hasFinal) {
                throw new InvalidArgumentException('Invalid substring ordering');
            }
            if ($position === 0 && $part['tag'] === 128) {
                $pattern = '^';
            } else {
                $pattern .= '.*';
            }
            $pattern .= preg_quote($part['value'], '~');
            $hasFinal = $part['tag'] === 130;
            $position++;
        }
        if ($hasFinal) {
            $pattern .= '$';
        }
        return static function (array $attributes) use ($attribute, $pattern): bool {
            foreach ($attributes[$attribute] ?? [] as $candidate) {
                if (preg_match('~' . $pattern . '~isD', $candidate) === 1) {
                    return true;
                }
            }
            return false;
        };
    }

    /**
     * Tests canonical directory DNs against the requested search scope.
     * @private
     * @ignore
     * @param string $dn Lowercase entry DN.
     * @param string $base Lowercase base DN.
     * @param int $scope LDAP search scope.
     * @return bool Whether the entry is in scope.
     */
    private function _withinScope(string $dn, string $base, int $scope): bool
    {
        if ($scope === 0) {
            return $dn === $base;
        }
        if ($dn === $base) {
            return $scope === 2;
        }
        if (!str_ends_with($dn, ',' . $base)) {
            return false;
        }
        $relative = substr($dn, 0, -strlen(',' . $base));
        return $scope === 2 || !str_contains($relative, ',');
    }

    /**
     * Rejects unknown critical controls and validates the control envelope.
     * @private
     * @ignore
     * @param string $message Remaining LDAPMessage contents.
     * @return bool Whether an unsupported critical control is present.
     */
    private function _hasCriticalControl(string $message): bool
    {
        $controls = Ber::Read($message, 160)['value'];
        if ($message !== '' || $controls === '') {
            throw new InvalidArgumentException('Invalid LDAP controls');
        }
        $critical = false;
        while ($controls !== '') {
            $control = Ber::Read($controls, 48)['value'];
            Ber::Read($control, 4);
            if ($control !== '' && ord($control[0]) === 1) {
                $boolean = Ber::Read($control, 1)['value'];
                if (strlen($boolean) !== 1) {
                    throw new InvalidArgumentException('Invalid criticality');
                }
                $critical = $critical || ord($boolean) !== 0;
            }
            if ($control !== '') {
                Ber::Read($control, 4);
            }
            if ($control !== '') {
                throw new InvalidArgumentException('Trailing control data');
            }
        }
        return $critical;
    }

    /**
     * Encodes a SearchResultEntry with explicit attribute selection.
     * @private
     * @ignore
     * @param int $messageId Request message ID.
     * @param string $dn Entry DN.
     * @param array $attributes Entry attributes.
     * @param array $selection Requested attributes.
     * @param bool $typesOnly Whether values must be omitted.
     * @return string Encoded entry message.
     */
    private function _entry(int $messageId, string $dn, array $attributes, array $selection, bool $typesOnly): string
    {
        $encoded = '';
        foreach ($attributes as $name => $values) {
            $operational = in_array($name, ['entryuuid', 'memberof', 'namingcontexts', 'supportedldapversion', 'vendorname'], true);
            $allUser = $selection === [] || in_array('*', $selection, true);
            $allOperational = in_array('+', $selection, true);
            if (!in_array($name, $selection, true) && !(($operational && $allOperational) || (!$operational && $allUser))) {
                continue;
            }
            $encodedValues = '';
            if (!$typesOnly) {
                foreach ($values as $value) {
                    $encodedValues .= Ber::Encode(4, $value);
                }
            }
            $encoded .= Ber::Encode(48, Ber::Encode(4, $name) . Ber::Encode(49, $encodedValues));
        }
        return Ber::Encode(48, Ber::Integer($messageId) . Ber::Encode(100, Ber::Encode(4, $dn) . Ber::Encode(48, $encoded)));
    }

    /**
     * Encodes an LDAPResult without leaking internal exception details.
     * @private
     * @ignore
     * @param int $messageId Request message ID.
     * @param int $tag Response operation tag.
     * @param int $code LDAP result code.
     * @param string $diagnostic Public diagnostic text.
     * @return string Encoded result message.
     */
    private function _result(int $messageId, int $tag, int $code, string $diagnostic = ''): string
    {
        $result = Ber::Integer($code, 10) . Ber::Encode(4, '') . Ber::Encode(4, $diagnostic);
        return Ber::Encode(48, Ber::Integer($messageId) . Ber::Encode($tag, $result));
    }
}