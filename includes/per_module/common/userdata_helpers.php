<?php

class UniEngineException extends \Exception {};
class UniEngineDataFetchException extends UniEngineException {};
class UniEnginePlanetDataFetchException extends UniEngineDataFetchException {};

//  Arguments
//      - $user (&Object)
//      - $params (Object)
//          - timestamp (Number)
//
//  Returns:
//      Boolean (is user currently blocked)
//
function handleUserBlockadeByCookie(&$user, $params) {
    $cookieKey = COOKIE_BLOCK;
    $cookieBlockSalt = COOKIE_BLOCK_VAL;

    $userID = $user['id'];
    $hasCookiesBlockade = ($user['block_cookies'] == 1);
    $timestamp = $params['timestamp'];

    if ($hasCookiesBlockade) {
        _blockUserByCookies($userID, $timestamp);

        return true;
    }

    if (empty($_COOKIE[$cookieKey])) {
        return false;
    }

    $cookieBlockHash = ($cookieBlockSalt . md5($userID));

    if (
        $_COOKIE[$cookieKey] === $cookieBlockHash &&
        $user['block_cookies'] == 0
    ) {
        // User was previously blocked, but not the blockade has been lifted
        _unblockUserByCookies($timestamp);

        return false;
    }

    return true;
}

//  Arguments
//      - $user (&Object)
//      - $params (Object)
//          - timestamp (Number)
//
//  Returns:
//      Boolean (has user been kicked out)
//
function handleUserKick(&$user, $params) {
    $userID = $user['id'];
    $isKickedOut = ($user['dokick'] == 1);
    $timestamp = $params['timestamp'];

    if (!$isKickedOut) {
        return false;
    }

    $sessionTimestamp = $timestamp - 100000;

    $query_KickUser = (
        "UPDATE {{table}} " .
        "SET " .
        "  `dokick` = 0 " .
        "WHERE " .
        "  `id` = {$userID} " .
        "LIMIT 1;"
    );

    doquery($query_KickUser, 'users');
    setcookie(getSessionCookieKey(), '', $sessionTimestamp, '/', '', 0);

    return true;
}

function _unblockUserByCookies($timestamp) {
    $cookieKey = COOKIE_BLOCK;
    $pastTimestamp = ($timestamp - 100000);

    setcookie($cookieKey, '', $pastTimestamp, '', '', false, true);
    $_COOKIE[$cookieKey] = null;
}

function _blockUserByCookies($userID, $timestamp) {
    $cookieKey = COOKIE_BLOCK;
    $cookieBlockSalt = COOKIE_BLOCK_VAL;
    $cookieBlockVal = ($cookieBlockSalt . md5($userID));
    $cookieTimestamp = ($timestamp + (3 * TIME_YEAR));

    setcookie($cookieKey, $cookieBlockVal, $cookieTimestamp, '', '', false, true);
}

function _fetchPlanetData($planetID) {
    $query_GetPlanet = (
        "SELECT * " .
        "FROM {{table}} " .
        "WHERE " .
        "  `id` = {$planetID} " .
        "LIMIT 1;"
    );
    $result_GetPlanet = doquery($query_GetPlanet, 'planets', true);

    return $result_GetPlanet;
}

function _isPlanetOwner($userID, &$planet) {
    return $planet['id_owner'] == $userID;
}

function fetchCurrentPlanetData (&$user) {
    $userID = $user['id'];
    $currentPlanetID = $user['current_planet'];
    $motherPlanetID = $user['id_planet'];

    $planet = _fetchPlanetData($currentPlanetID);

    if (
        (!$planet || !_isPlanetOwner($userID, $planet)) &&
        $currentPlanetID != $motherPlanetID
    ) {
        // TODO: determine is this is needed
        //       by checking how many places allow you to change 'current_planet'
        //
        // If this planet doesn't exist, try to go back to MotherPlanet
        SetSelectedPlanet($user, $motherPlanetID);

        $planet = _fetchPlanetData($motherPlanetID);
    }

    if (!$planet) {
        throw new UniEnginePlanetDataFetchException('Could not select the current, nor mother, planets');
    }

    CheckPlanetUsedFields($planet);

    return $planet;
}

function fetchGalaxyData(&$planet) {
    $planetID = $planet['id'];

    $selectorKey = (
        $planet['type'] == 1 ?
        'id_planet' :
        'id_moon'
    );

    $query_GetGalaxyRow = (
        "SELECT * " .
        "FROM {{table}} " .
        "WHERE " .
        "  `{$selectorKey}` = {$planetID} " .
        "LIMIT 1;"
    );

    $result_GetGalaxyRow = doquery($query_GetGalaxyRow, 'galaxy', true);

    return $result_GetGalaxyRow;
}

?>
