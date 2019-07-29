<?php

//  Arguments:
//      - $elementID (Number)
//      - $planet (&Object)
//      - $user (&Object)
//      - $params (Object)
//          - isBoosted (Boolean) [default: true]
//              Should boosters be pre-applied
//          - timerange (Object) [default: []]
//              - end (Number) [default: 0]
//              - start (Number) [default: timerange.end]
//          - customLevel (Number) [default: null]
//          - customProductionFactor (Number) [default: null]
//
function getElementProduction($elementID, &$planet, &$user, $params) {
    global $_GameConfig;

    $production = [
        'metal' => 0,
        'crystal' => 0,
        'deuterium' => 0,
        'energy' => 0
    ];

    if (!isset($params['isBoosted'])) {
        $params['isBoosted'] = true;
    }
    if (!isset($params['timerange'])) {
        $params['timerange'] = [];
    }
    if (!isset($params['timerange']['end'])) {
        $params['timerange']['end'] = 0;
    }
    if (!isset($params['timerange']['start'])) {
        $params['timerange']['start'] = $params['timerange']['end'];
    }
    if (!isset($params['customLevel'])) {
        $params['customLevel'] = null;
    }
    if (!isset($params['customProductionFactor'])) {
        $params['customProductionFactor'] = null;
    }

    $isBoosted = $params['isBoosted'];
    $timerange = $params['timerange'];
    $hasCustomLevel = ($params['customLevel'] !== null);
    $customLevel = $params['customLevel'];
    $hasCustomProductionFactor = ($params['customProductionFactor'] !== null);
    $customProductionFactor = $params['customProductionFactor'];

    if (
        !_isElementStructure($elementID) &&
        !_isElementShip($elementID)
    ) {
        return $production;
    }

    $productionFormula = _getElementProductionFormula($elementID);

    if (!is_callable($productionFormula)) {
        return $production;
    }

    $elementPlanetKey = _getElementPlanetKey($elementID);
    $elementParams = [
        'level' => (
            $hasCustomLevel ?
            $customLevel :
            $planet[$elementPlanetKey]
        ),
        'productionFactor' => (
            $hasCustomProductionFactor ?
            $customProductionFactor :
            _getElementPlanetProductionFactor($elementID, $planet)
        ),
        'planetTemp' => $planet['temp_max']
    ];

    $elementProduction = $productionFormula($elementParams);

    $boostersIncrease = [
        'geologist' => 0,
        'engineer' => 0
    ];

    if ($isBoosted) {
        // FIXME: remove applicability ratios feature - production updaters
        // should take care of this on their own, since there are multiple
        // periods that may split the ranges, not just "one for all"
        $boostersIncrease['geologist'] = (
            0.15 *
            _getBoosterApplicabilityRatio('geologist', $timerange, $user)
        );
        $boostersIncrease['engineer'] = (
            0.10 *
            _getBoosterApplicabilityRatio('engineer', $timerange, $user)
        );
    }

    foreach ($elementProduction as $resourceKey => $resourceProduction) {
        if (_isResourceProductionSpeedMultiplicable($resourceKey)) {
            $resourceProduction *= $_GameConfig['resource_multiplier'];
        }

        if ($resourceProduction <= 0) {
            // TODO: correctly calculate when there is fuel usage
            // eg. as in Fusion Reactor

            $production[$resourceKey] = $resourceProduction;

            continue;
        }

        if (_isResourceBoosterApplicable($resourceKey, 'geologist')) {
            $resourceProduction *= (1 + $boostersIncrease['geologist']);
        }

        if (_isResourceBoosterApplicable($resourceKey, 'engineer')) {
            $resourceProduction *= (1 + $boostersIncrease['engineer']);
        }

        $production[$resourceKey] = $resourceProduction;
    }

    foreach ($production as $resourceKey => $resourceProduction) {
        $production[$resourceKey] = floor($resourceProduction);
    }

    return $production;
}

function getElementProducedResourceKeys($elementID) {
    $theoreticalProduction = _getTheoreticalElementProduction($elementID);

    $producedResourceKeys = [];

    foreach ($theoreticalProduction as $resourceKey => $resourceProduction) {
        if ($resourceProduction <= 0) {
            continue;
        }

        $producedResourceKeys[] = $resourceKey;
    }

    return $producedResourceKeys;
}

function getElementConsumedResourceKeys($elementID) {
    $theoreticalProduction = _getTheoreticalElementProduction($elementID);

    $consumedResourceKeys = [];

    foreach ($theoreticalProduction as $resourceKey => $resourceProduction) {
        if ($resourceProduction >= 0) {
            continue;
        }

        $consumedResourceKeys[] = $resourceKey;
    }

    return $consumedResourceKeys;
}

function _getTheoreticalElementProduction($elementID) {
    if (
        !_isElementStructure($elementID) &&
        !_isElementShip($elementID)
    ) {
        return [];
    }

    $productionFormula = _getElementProductionFormula($elementID);

    if (!is_callable($productionFormula)) {
        return [];
    }

    $elementParams = [
        'level' => 1,
        'productionFactor' => 10,
        'planetTemp' => 100
    ];

    return $productionFormula($elementParams);
}

function _isResourceProductionSpeedMultiplicable($resourceKey) {
    $multiplicableResources = [
        'metal',
        'crystal',
        'deuterium'
    ];

    return in_array($resourceKey, $multiplicableResources);
}

function _isResourceBoosterApplicable($resourceKey, $boosterKey) {
    $applicabilityMatrix = [
        'geologist' => [
            'metal',
            'crystal',
            'deuterium'
        ],
        'engineer' => [
            'energy'
        ]
    ];

    $applicabilityTable = $applicabilityMatrix[$boosterKey];

    return in_array($resourceKey, $applicabilityTable);
}

function _isElementStructure($elementID) {
    global $_Vars_ElementCategories;

    return in_array($elementID, $_Vars_ElementCategories['build']);
}

function _isElementShip($elementID) {
    global $_Vars_ElementCategories;

    return in_array($elementID, $_Vars_ElementCategories['fleet']);
}

function _getElementPlanetKey($elementID) {
    global $_Vars_GameElements;

    return $_Vars_GameElements[$elementID];
}

function _getElementPlanetProductionFactor($elementID, &$planet) {
    $elementPlanetKey = _getElementPlanetKey($elementID);
    $productionFactorKey = $elementPlanetKey . '_workpercent';

    return $planet[$productionFactorKey];
}

function _getElementProductionFormula($elementID) {
    global $_Vars_ResProduction;

    if (!isset($_Vars_ResProduction[$elementID]['production'])) {
        return null;
    }

    return $_Vars_ResProduction[$elementID]['production'];
}

function _getBoosterEndtime($boosterKey, &$user) {
    $boosterEndtimeKey = $boosterKey . '_time';

    return $user[$boosterEndtimeKey];
}

//  Arguments:
//      - $boosterKey (String)
//      - $timerange (Object)
//          - start (Number)
//          - end (Number)
//      - $user (&Object)
//
function _getBoosterApplicabilityRatio($boosterKey, $timerange, &$user) {
    $boosterEndtime = _getBoosterEndtime($boosterKey, $user);

    if ($boosterEndtime <= $timerange['start']) {
        return 0;
    }

    if ($boosterEndtime >= $timerange['end']) {
        return 1;
    }

    $applicableInRange = ($boosterEndtime - $timerange['start']);
    $rangeLength = ($timerange['end'] - $timerange['start']);

    return ($applicableInRange / $rangeLength);
}

?>
