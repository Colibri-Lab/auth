<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\Applications;
use App\Modules\Auth\Models\AutoLogins;
use App\Modules\Auth\Models\Members;
use App\Modules\Auth\Models\Sessions;
use App\Modules\Auth\Module;
use Colibri\App;
use Colibri\AppException;
use Colibri\Data\Storages\Storages;
use Colibri\Data\DataAccessPoint;
use Colibri\Data\SqlClient\QueryInfo;
use Colibri\Utils\Cache\Mem;
use Colibri\Exceptions\PermissionDeniedException;
use Colibri\Exceptions\ValidationException;
use Colibri\Web\Controller as WebController;
use Colibri\Web\PayloadCopy;
use Colibri\Web\RequestCollection;
use InvalidArgumentException;

/**
 * Application controller
 * @class
 * @memberof App\Modules\Auth\Controllers
 * @extends WebController
 */
class BackendController extends WebController
{

    /**
     * Request autologin for member
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     * @public
     */
    public function RequestAutologin(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $result = [];
        $message = 'Result message';
        $code = 200;

        try {

            if(App::$domainKey != 'manage') {
                throw new PermissionDeniedException('Permission denied');
            }
            
            $app = Applications::LoadByToken($post->{'app'});
            if(!$app->params->autologin) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-autologin}', 'code' => 400]);
            }


            $payloadArray = $payload->ToArray();
            $token = $payloadArray['token'] ?? $post->{'token'};
            $returnTo = $payloadArray['return'] ?? $post->{'return'};
            if(!$token) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-provide-token}', 'code' => 400]);
            }

            $member = Members::LoadByToken($token);
            if(!$member) {
                return $this->Finish(400, 'Bad Request', ['message' => '#{auth-errors-member-not-found}', 'code' => 400]);
            }

            $autologin = AutoLogins::CreateForMember($app, $member, $returnTo);
            $result['link'] = $autologin->GenerateLink($app);
            

        } catch (InvalidArgumentException $e) {
            return $this->Finish(400, 'Bad request', ['message' => $e->getMessage(), 'code' => 400]);
        } catch (ValidationException $e) {
            return $this->Finish(500, 'Application validation error', ['message' => $e->getMessage(), 'code' => 400, 'data' => $e->getExceptionDataAsArray()]);
        } catch (\Throwable $e) {
            return $this->Finish(500, 'Application error', ['message' => $e->getMessage(), 'code' => 500]);
        }
            
        return $this->Finish(
            $code,
            $message,
            $result,
            'utf-8'
        );

    }


    
    /**
     * undefined
     * @param RequestCollection $get данные GET
     * @param RequestCollection $post данные POST
     * @param mixed $payload данные payload обьекта переданного через POST/PUT
     * @return object
     * @public
     */
    public function ChangePassword(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        $result = [];
        $message = 'Result message';
        $code = 200;

        if(App::$domainKey != 'manage') {
            throw new PermissionDeniedException('Permission denied');
        }
            
        $member = Members::LoadByToken($post->{'member'});
        if($member) {
            $member->password = $post->{'password'};
            if (($res = $member->Save(true)) !== true) {
                /** @var \Colibri\Data\SqlClient\QueryInfo $res */
                throw new InvalidArgumentException($res->error, 400);
            }
        }
            
        return $this->Finish(
            $code,
            $message,
            $result,
            'utf-8'
        );

    }


    
    /**
     * Migrates legacy member roles to JSON arrays through a CLI-only invocation
     * @public
     * @param RequestCollection $get RequestCollection containing GET data
     * @param RequestCollection $post RequestCollection containing POST data
     * @param ?PayloadCopy $payload Request payload data as PayloadCopy object
     * @return object
     */
    public function MigrateMemberRoles(RequestCollection $get, RequestCollection $post, ? PayloadCopy $payload = null): object
    {

        if (PHP_SAPI !== 'cli' || App::$request->server->{'request_method'} || App::$domainKey !== 'manage') {
            throw new PermissionDeniedException('Role migration requires a Manage CLI invocation', 403);
        }

        $storage = Storages::Instance()->Load('members', 'Auth');
        $table = $storage->table;
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) {
            throw new InvalidArgumentException('Invalid members table name');
        }
        $point = $storage->accessPoint;
        $reader = $point->Query('SHOW COLUMNS FROM `' . $table . '` WHERE Field = \'members_role\'');
        if ($reader instanceof QueryInfo) {
            throw new InvalidArgumentException($reader->error);
        }
        $column = $reader->Read();
        if (!$column) {
            throw new InvalidArgumentException('Member role column not found');
        }
        if ($column->Type !== 'json') {
            // Keep the original column intact until the complete conversion is ready.
            foreach ([
                'ALTER TABLE `' . $table . '` ADD COLUMN `members_role_array` JSON NULL',
                'UPDATE `' . $table . '` SET `members_role_array` = CASE '
                    . 'WHEN `members_role` IS NULL OR `members_role` = \'\' THEN JSON_ARRAY() '
                    . 'ELSE JSON_ARRAY(JSON_OBJECT(\'role\', `members_role`)) END'
            ] as $query) {
                $result = $point->Query($query, ['type' => DataAccessPoint::QueryTypeNonInfo]);
                if ($result->error) {
                    throw new InvalidArgumentException($result->error);
                }
            }
            $reader = $point->Query('SELECT COUNT(*) AS invalid FROM `' . $table . '` '
                . 'WHERE NOT (`members_role_array` <=> CASE '
                . 'WHEN `members_role` IS NULL OR `members_role` = \'\' THEN JSON_ARRAY() '
                . 'ELSE JSON_ARRAY(JSON_OBJECT(\'role\', `members_role`)) END)');
            if ($reader instanceof QueryInfo) {
                throw new InvalidArgumentException($reader->error);
            }
            if ((int)$reader->Read()->invalid !== 0) {
                throw new InvalidArgumentException('Role conversion verification failed');
            }
            $result = $point->Query('ALTER TABLE `' . $table . '` DROP COLUMN `members_role`, '
                . 'CHANGE COLUMN `members_role_array` `members_role` JSON NULL DEFAULT (JSON_ARRAY())',
                ['type' => DataAccessPoint::QueryTypeNonInfo]);
            if ($result->error) {
                throw new InvalidArgumentException($result->error);
            }
        }

        $reader = $point->Query('SELECT COUNT(*) AS invalid FROM `' . $table . '` '
            . 'WHERE `members_role` IS NOT NULL AND JSON_TYPE(`members_role`) <> \'ARRAY\'');
        if ($reader instanceof QueryInfo) {
            throw new InvalidArgumentException($reader->error);
        }
        if ((int)$reader->Read()->invalid !== 0) {
            throw new InvalidArgumentException('Member roles contain non-array JSON values');
        }
        Storages::Instance()->ClearCache();
        foreach (App::$config->Query('hosts.domains')->ToArray() as $domain => $hosts) {
            foreach ($hosts as $host) {
                Mem::Delete('storages' . $domain . $host);
            }
        }
        return $this->Finish(200, 'ok', ['role_type' => 'json']);

    }
}