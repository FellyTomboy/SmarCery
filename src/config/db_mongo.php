<?php
/**
 * MongoDB connection.
 * Returns a singleton MongoDB\Client instance.
 *
 * Requires: composer require mongodb/mongodb
 */

function db_mongo(): MongoDB\Client {
    static $client = null;
    if ($client === null) {
        $uri = getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017';
        $client = new MongoDB\Client($uri);
    }
    return $client;
}

function mongo_db(): MongoDB\Database {
    return db_mongo()->selectDatabase('smarcery');
}