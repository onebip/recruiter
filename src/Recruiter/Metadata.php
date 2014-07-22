<?php

namespace Recruiter;

use MongoDate;
use MongoDuplicateKeyException;

class Metadata
{
    private $collection;
    private $lockDocumentId;

    public function __construct($db)
    {
        $this->collection = $db->selectCollection('metadata');
        $this->lockDocumentId = 'RECRUITER_PROCESS';
    }

    public function get()
    {
        return $this->collection->findOne(['_id' => $this->lockDocumentId]);
    }

    public function initialize($token, $pid)
    {
        return $this->isOk(function() use ($token, $pid) {
            return $this->collection->insert([
                '_id' => $this->lockDocumentId,
                'started_at' => new MongoDate(),
                'token' => $token,
                'pid' => $pid,
            ]);
        });
    }

    public function lock($controlToken, $currentToken, $pid)
    {
        return $this->isOk(function() use ($controlToken, $currentToken, $pid) {
            return $this->collection->update(
                ['_id' => $this->lockDocumentId, 'token' => $controlToken],
                ['$set' => [
                    'started_at' => new MongoDate(),
                    'token' => $currentToken,
                    'pid' => $pid,
                ]]
            );
        });
    }

    private function isOk($operation)
    {
        try {
            $result = $operation();
            if (is_bool($result)) {
                return $result;
            }
            if (is_array($result)) {
                return array_key_exists('ok', $result) && ($result['ok'] == 1);
            }
            return false;

        } catch (MongoDuplicateKeyException $e) {
            return false;
        }
    }
}
