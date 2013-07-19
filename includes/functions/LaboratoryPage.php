<?php

function LaboratoryPage(&$CurrentPlanet, $CurrentUser, $InResearch, $ThePlanet)
{
	global $_EnginePath, $_Lang, $_Vars_GameElements, $_Vars_ElementCategories, $_SkinPath, $_GameConfig, $_GET;
	
	include($_EnginePath.'includes/functions/GetElementTechReq.php');
	
	$Now = time();
	$Parse = &$_Lang;
	$ShowElementID = 0;

	// Constants
	$ElementsPerRow = 7;

	// Get Templates
	$TPL['list_element']				= gettemplate('buildings_compact_list_element_lab');
	$TPL['list_levelmodif']				= gettemplate('buildings_compact_list_levelmodif');
	$TPL['list_hidden']					= gettemplate('buildings_compact_list_hidden');
	$TPL['list_row']					= gettemplate('buildings_compact_list_row');
	$TPL['list_breakrow']				= gettemplate('buildings_compact_list_breakrow');
	$TPL['list_disabled']				= gettemplate('buildings_compact_list_disabled');
	$TPL['list_partdisabled']			= parsetemplate($TPL['list_disabled'], array('AddOpacity' => 'dPart'));
	$TPL['list_disabled']				= parsetemplate($TPL['list_disabled'], array('AddOpacity' => ''));
	$TPL['queue_topinfo']				= gettemplate('buildings_compact_queue_topinfo');
	$TPL['queue_planetlink']			= gettemplate('buildings_compact_queue_planetlink');
	$TPL['infobox_body']				= gettemplate('buildings_compact_infobox_body_lab');
	$TPL['infobox_levelmodif']			= gettemplate('buildings_compact_infobox_levelmodif');
	$TPL['infobox_req_res']				= gettemplate('buildings_compact_infobox_req_res');
	$TPL['infobox_req_desttable']		= gettemplate('buildings_compact_infobox_req_desttable');
	$TPL['infobox_req_destres']			= gettemplate('buildings_compact_infobox_req_destres');
	$TPL['infobox_additionalnfo']		= gettemplate('buildings_compact_infobox_additionalnfo');
	$TPL['infobox_req_selector_single'] = gettemplate('buildings_compact_infobox_req_selector_single');
	$TPL['infobox_req_selector_dual']	= gettemplate('buildings_compact_infobox_req_selector_dual');

	if($CurrentPlanet[$_Vars_GameElements[31]] > 0)
	{
		$HasLab = true;
	}
	else
	{
		$HasLab = false;
	}
	
	// Get OtherPlanets with Lab
	$Query_GetOtherLabs .= "SELECT `id`, `buildQueue`, `{$_Vars_GameElements[31]}` FROM {{table}} ";
	$Query_GetOtherLabs .= "WHERE `id_owner` = {$CurrentUser['id']} AND `planet_type` = 1;";
	$Result_GetOtherLabs = doquery($Query_GetOtherLabs, 'planets');
	if(mysql_num_rows($Result_GetOtherLabs) > 0)
	{
		while($FetchData = mysql_fetch_assoc($Result_GetOtherLabs))
		{
			if(!empty($FetchData['buildQueue']))
			{
				if(substr($FetchData['buildQueue'], 0, 3) == '31,' OR strstr($FetchData['buildQueue'], ';31,') !== false)
				{
					$LabInQueue_CheckID = $FetchData['id'];
				}
			}
			if($FetchData[$_Vars_GameElements[31]] > 0)
			{
				$OtherLabs_Levels[] = $FetchData[$_Vars_GameElements[31]];
			}
		}
		if(!empty($OtherLabs_Levels))
		{
			rsort($OtherLabs_Levels);
			$OtherLabs_ConnectedLabsCount = 1 + $CurrentUser[$_Vars_GameElements[123]];
			$OtherLabs_ConnectedLabs = 0;
			foreach($OtherLabs_Levels as $ThisLabLevel)
			{
				if($OtherLabs_ConnectedLabs < $OtherLabs_ConnectedLabsCount)
				{
					$OtherLabs_ConnectedLabsLevel += $ThisLabLevel;
					$OtherLabs_ConnectedLabs += 1;
				}
				$OtherLabs_TotalLabsLevel += $ThisLabLevel;
			}
			$OtherLabs_LabsCount = count($OtherLabs_Levels);
		}
	}
	
	// Check if Lab is in BuildQueue
	$LabInQueue = false;
	if($_GameConfig['BuildLabWhileRun'] != 1 AND $LabInQueue_CheckID > 0)
	{
		include($_EnginePath.'/includes/functions/CheckLabInQueue.php');
		
		$LabInQueue_CheckPlanet = doquery("SELECT * FROM {{table}} WHERE `id` = {$LabInQueue_CheckID} LIMIT 1;", 'planets', true);
		
		$Results['planets'] = array();
		// Update Planet - Building Queue
		$CheckLab = CheckLabInQueue($LabInQueue_CheckPlanet);
		if($CheckLab !== false)
		{
			if($CheckLab <= $Now)
			{
				if(HandlePlanetQueue($LabInQueue_CheckPlanet, $CurrentUser, $Now, true) === true)
				{
					$Results['planets'][] = $LabInQueue_CheckPlanet;
				}
			}
			else
			{
				$LabInQueueAt[] = parsetemplate($TPL['queue_planetlink'], array
				(
					'PlanetID' => $LabInQueue_CheckPlanet['id'],
					'PlanetName' => $LabInQueue_CheckPlanet['name'],
					'PlanetCoords' => "{$LabInQueue_CheckPlanet['galaxy']}:{$LabInQueue_CheckPlanet['system']}:{$LabInQueue_CheckPlanet['planet']}"
				));
				$LabInQueue = true;
			} 
		}
		HandlePlanetUpdate_MultiUpdate($Results, $CurrentUser);
	}

	PlanetResourceUpdate($CurrentUser, $CurrentPlanet, $Now);

	if(is_array($ThePlanet))
	{
		$ResearchPlanet = &$ThePlanet;
	}
	else
	{
		$ResearchPlanet = &$CurrentPlanet;
	}

	// Execute Commands
	if(!isOnVacation($CurrentUser))
	{
		if(isset($_GET['cmd']))
		{
			if($LabInQueue === false)
			{
				$TheCommand = $_GET['cmd'];
				$TechID = intval($_GET['tech']);
				$QueueElementID = intval($_GET['el']);

				if((in_array($TechID, $_Vars_ElementCategories['tech']) AND $TheCommand == 'search') OR ($TheCommand == 'cancel' AND $QueueElementID >= 0))
				{
					// Parse Commands
					if($TheCommand == 'cancel')
					{
						// User requested cancel Action
						include($_EnginePath.'includes/functions/TechQueue_Remove.php');
						$ShowElementID = TechQueue_Remove($ResearchPlanet, $CurrentUser, $QueueElementID, $Now);
						if($ShowElementID !== false AND $CurrentUser['techQueue_Planet'] == '0')
						{
							$UpdateUser = &$CurrentUser;
						}
						else
						{
							$UpdateUser = false;
						}
						$CommandDone = true;
					}
					else if($TheCommand == 'search')
					{
						// User requested do the research
						include($_EnginePath.'includes/functions/TechQueue_Add.php');
						TechQueue_Add($ResearchPlanet, $CurrentUser, $TechID);
						$ShowElementID = $TechID;
						$CommandDone = true;
					}

					if($CommandDone === true)
					{
						if(HandlePlanetQueue_TechnologySetNext($ResearchPlanet, $CurrentUser, $Now, true) === false)
						{
							include($_EnginePath.'includes/functions/PostResearchSaveChanges.php');
							PostResearchSaveChanges($ResearchPlanet, ($ResearchPlanet['id'] == $CurrentPlanet['id'] ? true : false), $UpdateUser);
						}
					}
				}
			}
		}
	}

	if($ResearchPlanet['id'] != $CurrentPlanet['id'] AND $InResearch === true)
	{
		$ResearchInThisLab = false;
	}
	else
	{
		$ResearchInThisLab = true;
	}
	// End of - Execute Commands

	// Parse Queue
	$CurrentQueue = $ResearchPlanet['techQueue'];
	if(!empty($CurrentQueue))
	{
		$CurrentQueue = explode(';', $CurrentQueue);
		$QueueIndex = 0;
		foreach($CurrentQueue as $QueueID => $QueueData)
		{
			$QueueData = explode(',', $QueueData);
			$BuildEndTime = $QueueData[3];
			if($BuildEndTime >= $Now)
			{
				$ListID = $QueueIndex;
				$ElementID = $QueueData[0];
				$ElementLevel = $QueueData[1];
				$ElementBuildtime = $BuildEndTime - $Now;
				$ElementName = $_Lang['tech'][$ElementID];
				if($QueueIndex == 0)
				{
					include($_EnginePath.'/includes/functions/InsertJavaScriptChronoApplet.php');

					$QueueParser[] = array
					(
						'ChronoAppletScript'	=> InsertJavaScriptChronoApplet('QueueFirstTimer', '', $BuildEndTime, true, false, 'function() { $(\"#QueueCancel\").html(\"'.$_Lang['Queue_Cancel_Go'].'\").attr(\"href\", \"buildings.php?mode=research\").removeClass(\"cancelQueue\").addClass(\"lime\"); SetTimer = \"<b class=lime>'.$_Lang['completed'].'</b>\"; window.setTimeout(\'document.location.href=\"buildings.php?mode=research\";\', 1000); }'),
						'EndTimer'				=> pretty_time($ElementBuildtime, true),
						'SkinPath'				=> $_SkinPath,
						'ElementID'				=> $ElementID,
						'Name'					=> $ElementName,
						'LevelText'				=> $_Lang['level'],
						'Level'					=> $ElementLevel,
						'PlanetID'				=> $ResearchPlanet['id'],
						'PlanetImg'				=> $ResearchPlanet['image'],
						'Queue_ResearchOn'		=> $_Lang['Queue_ResearchOn'],
						'PlanetLabColor'		=> ($ResearchInThisLab ? 'lime' : 'orange'),
						'PlanetName'			=> $ResearchPlanet['name'],
						'PlanetCoords'			=> "{$ResearchPlanet['galaxy']}:{$ResearchPlanet['system']}:{$ResearchPlanet['planet']}",
						'EndText'				=> $_Lang['Queue_EndTime'],
						'EndDate'				=> date('d/m | H:i:s', $BuildEndTime),
						'EndTitleBeg'			=> $_Lang['Queue_EndTitleBeg'],
						'EndTitleHour'			=> $_Lang['Queue_EndTitleHour'],
						'EndDateExpand'			=> prettyDate('d m Y', $BuildEndTime, 1),
						'EndTimeExpand'			=> date('H:i:s', $BuildEndTime),
						'PremBlock'				=> ($_Vars_PremiumBuildings[$ElementID] == 1 ? 'premblock' : ''),
						'CancelText'			=> ($_Vars_PremiumBuildings[$ElementID] == 1 ? $_Lang['Queue_Cancel_CantCancel'] : $_Lang['Queue_Cancel_Research'])
					);
				}
				else
				{
					$QueueParser[] = array
					(
						'ElementNo'			=> $ListID + 1,
						'ElementID'			=> $ElementID,
						'Name'				=> $ElementName,
						'LevelText'			=> $_Lang['level'],
						'Level'				=> $ElementLevel,
						'EndDate'			=> date('d/m H:i:s', $BuildEndTime),
						'EndTitleBeg'		=> $_Lang['Queue_EndTitleBeg'],
						'EndTitleHour'		=> $_Lang['Queue_EndTitleHour'],
						'EndDateExpand'		=> prettyDate('d m Y', $BuildEndTime, 1),
						'EndTimeExpand'		=> date('H:i:s', $BuildEndTime),
						'InfoBox_BuildTime' => $_Lang['InfoBox_ResearchTime'],
						'BuildTime'			=> pretty_time($BuildEndTime - $PreviousBuildEndTime),
						'ListID'			=> $ListID,
						'RemoveText'		=> $_Lang['Queue_Cancel_Remove']
					);

					$GetResourcesToLock = GetBuildingPrice($CurrentUser, $CurrentPlanet, $ElementID, true, false);
					$LockResources['metal'] += $GetResourcesToLock['metal'];
					$LockResources['crystal'] += $GetResourcesToLock['crystal'];
					$LockResources['deuterium'] += $GetResourcesToLock['deuterium'];
				}

				$LevelModifiers[$ElementID] -= 1;
				$CurrentUser[$_Vars_GameElements[$ElementID]] += 1;

				$QueueIndex += 1;
			}
			$PreviousBuildEndTime = $BuildEndTime;
		}
		$CurrentPlanet['metal'] -= $LockResources['metal'];
		$CurrentPlanet['crystal'] -= $LockResources['crystal'];
		$CurrentPlanet['deuterium'] -= $LockResources['deuterium'];

		$Queue['lenght'] = $QueueIndex;
		if(!empty($QueueParser))
		{
			foreach($QueueParser as $QueueID => $QueueData)
			{
				if($QueueID == 0)
				{
					$ThisTPL = gettemplate('buildings_compact_queue_firstel_lab');
				}
				else if($QueueID == 1)
				{
					$ThisTPL = gettemplate('buildings_compact_queue_nextel_lab');
				}
				$Parse['Create_Queue'] .= parsetemplate($ThisTPL, $QueueData);
			}
		}
	}
	else
	{
		$Queue['lenght'] = 0;
		$Parse['Create_Queue'] = parsetemplate($TPL['queue_topinfo'], array('InfoText' => $_Lang['Queue_Empty']));
	}
	if($LabInQueue === false)
	{
		if($Queue['lenght'] < ((isPro($CurrentUser)) ? MAX_TECH_QUEUE_LENGTH_PRO : MAX_TECH_QUEUE_LENGTH))
		{
			$CanAddToQueue = true;
		}
		else
		{
			$CanAddToQueue = false;
			$Parse['Create_Queue'] = parsetemplate($TPL['queue_topinfo'], array('InfoColor' => 'red', 'InfoText' => $_Lang['Queue_Full'])).$Parse['Create_Queue'];
		} 
	}
	else
	{
		$Parse['Create_Queue'] = parsetemplate($TPL['queue_topinfo'], array('InfoColor' => 'red', 'InfoText' => sprintf($_Lang['Queue_LabInQueue'], implode('<br/>', $LabInQueueAt))));
	}
	// End of - Parse Queue

	$ResImages = array
	(
		'metal' => 'metall',
		'crystal' => 'kristall',
		'deuterium' => 'deuterium',
		'energy_max' => 'energie',
		'darkEnergy' => 'darkenergy'
	);
	$ResLangs = array
	(
		'metal' => $_Lang['Metal'],
		'crystal' => $_Lang['Crystal'],
		'deuterium' => $_Lang['Deuterium'],
		'energy_max' => $_Lang['Energy'],
		'darkEnergy' => $_Lang['DarkEnergy']
	);

	$ElementParserDefault = array
	(
		'SkinPath'					=> $_SkinPath,
		'InfoBox_Level'				=> $_Lang['InfoBox_Level'],
		'InfoBox_Build'				=> $_Lang['InfoBox_DoResearch'],
		'InfoBox_RequirementsFor'	=> $_Lang['InfoBox_RequirementsFor'],
		'InfoBox_ResRequirements'	=> $_Lang['InfoBox_ResRequirements'],
		'InfoBox_TechRequirements'	=> $_Lang['InfoBox_TechRequirements'],
		'InfoBox_Requirements_Res'	=> $_Lang['InfoBox_Requirements_Res'],
		'InfoBox_Requirements_Tech' => $_Lang['InfoBox_Requirements_Tech'],
		'InfoBox_BuildTime'			=> $_Lang['InfoBox_ResearchTime'],
		'InfoBox_ShowTechReq'		=> $_Lang['InfoBox_ShowTechReq'],
		'InfoBox_ShowResReq'		=> $_Lang['InfoBox_ShowResReq'],
	);

	foreach($_Vars_ElementCategories['tech'] as $ElementID)
	{
		$ElementParser = $ElementParserDefault;

		$CurrentLevel = $CurrentUser[$_Vars_GameElements[$ElementID]];
		$NextLevel = $CurrentUser[$_Vars_GameElements[$ElementID]] + 1;
		$MaxLevelReached = false;
		$TechLevelOK = false;
		$HasResources = true;

		$HideButton_Build = false;
		$HideButton_QuickBuild = false;

		$ElementParser['HideBuildWarn'] = 'hide';
		$ElementParser['ElementName'] = $_Lang['tech'][$ElementID];
		$ElementParser['ElementID'] = $ElementID;
		$ElementParser['ElementLevel'] = prettyNumber($CurrentUser[$_Vars_GameElements[$ElementID]]);
		$ElementParser['ElementRealLevel'] = prettyNumber($CurrentUser[$_Vars_GameElements[$ElementID]] + $LevelModifiers[$ElementID]);
		$ElementParser['BuildLevel'] = prettyNumber($CurrentUser[$_Vars_GameElements[$ElementID]] + 1);
		$ElementParser['Desc'] = $_Lang['res']['descriptions'][$ElementID];
		$ElementParser['BuildButtonColor'] = 'buildDo_Green';

		if(isset($LevelModifiers[$ElementID]))
		{
			$ElementParser['levelmodif']['modColor'] = 'lime';
			$ElementParser['levelmodif']['modText'] = '+'.prettyNumber($LevelModifiers[$ElementID] * (-1));
			$ElementParser['LevelModifier'] = parsetemplate($TPL['infobox_levelmodif'], $ElementParser['levelmodif']);
			$ElementParser['ElementLevelModif'] = parsetemplate($TPL['list_levelmodif'], $ElementParser['levelmodif']);
			unset($ElementParser['levelmodif']);
		}

		if(!($_Vars_MaxBuildingLevels[$ElementID] > 0 AND $NextLevel > $_Vars_MaxBuildingLevels[$ElementID]))
		{
			$ElementParser['ElementPrice'] = GetBuildingPrice($CurrentUser, $CurrentPlanet, $ElementID, true, false, true);
			foreach($ElementParser['ElementPrice'] as $Key => $Value)
			{
				if($Value > 0)
				{
					$ResColor = '';
					$ResMinusColor = '';
					$MinusValue = '&nbsp;';

					if($Key != 'darkEnergy')
					{
						$UseVar = &$CurrentPlanet;
					}
					else
					{
						$UseVar = &$CurrentUser;
					}
					if($UseVar[$Key] < $Value)
					{
						$ResMinusColor = 'red';
						$MinusValue = '('.prettyNumber($UseVar[$Key] - $Value).')';
						if($Queue['lenght'] > 0)
						{
							$ResColor = 'orange';
						}
						else
						{
							$ResColor = 'red';
						}
					}

					$ElementParser['ElementPrices'] = array
					(
						'SkinPath' => $_SkinPath,
						'ResName' => $Key,
						'ResImg' => $ResImages[$Key],
						'ResColor' => $ResColor,
						'Value' => prettyNumber($Value),
						'ResMinusColor' => $ResMinusColor,
						'MinusValue' => $MinusValue
					);
					$ElementParser['ElementPriceDiv'] .= parsetemplate($TPL['infobox_req_res'], $ElementParser['ElementPrices']);
				}
			}
			$ElementParser['BuildTime'] = pretty_time(GetBuildingTime($CurrentUser, $CurrentPlanet, $ElementID));
		}
		else
		{
			$MaxLevelReached = true;
			$ElementParser['HideBuildInfo'] = 'hide';
			$ElementParser['HideBuildWarn'] = '';
			$HideButton_Build = true;
			$ElementParser['BuildWarn_Color'] = 'red';
			$ElementParser['BuildWarn_Text'] = $_Lang['ListBox_Disallow_MaxLevelReached'];
		}
		if(IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $ElementID))
		{
			$TechLevelOK = true;
			$ElementParser['ElementRequirementsHeadline'] = $TPL['infobox_req_selector_single'];
		}
		else
		{
			$ElementParser['ElementRequirementsHeadline'] = $TPL['infobox_req_selector_dual'];
			$ElementParser['ElementTechDiv'] = GetElementTechReq($CurrentUser, $CurrentPlanet, $ElementID, true);
			$ElementParser['HideResReqDiv'] = 'hide';
		}
		if(IsElementBuyable($CurrentUser, $CurrentPlanet, $ElementID, true, false, true) === false)
		{
			$HasResources = false;
			if($Queue['lenght'] == 0)
			{
				$ElementParser['BuildButtonColor'] = 'buildDo_Gray';
				$HideButton_QuickBuild = true;
			}
			else
			{
				$ElementParser['BuildButtonColor'] = 'buildDo_Orange';
			}
		}
			
		$BlockReason = array();

		if($MaxLevelReached)
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_MaxLevelReached'];
		}
		else if(!$HasResources)
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_NoResources'];
		}
		if(!$TechLevelOK)
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_NoTech'];
			$ElementParser['BuildButtonColor'] = 'buildDo_Gray';
			$HideButton_QuickBuild = true;
		}
		if($CanAddToQueue === false)
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_QueueIsFull'];
			$ElementParser['BuildButtonColor'] = 'buildDo_Gray';
			$HideButton_QuickBuild = true;
		}
		if($HasLab === false)
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_NoLab'];
			$ElementParser['BuildButtonColor'] = 'buildDo_Gray';
			$HideButton_QuickBuild = true;
		}
		if($ResearchInThisLab === false)
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_NotThisLab'];
			$ElementParser['BuildButtonColor'] = 'buildDo_Gray';
			$HideButton_QuickBuild = true;
		}
		if($LabInQueue === true)
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_LabInQueue'];
			$ElementParser['BuildButtonColor'] = 'buildDo_Gray';
			$HideButton_QuickBuild = true;
		}
		if(isOnVacation($CurrentUser))
		{
			$BlockReason[] = $_Lang['ListBox_Disallow_VacationMode'];
			$ElementParser['BuildButtonColor'] = 'buildDo_Gray';
			$HideButton_QuickBuild = true;
		}

		if(!empty($BlockReason))
		{
			if($ElementParser['BuildButtonColor'] == 'buildDo_Orange')
			{
				$ElementParser['ElementDisabled'] = $TPL['list_partdisabled'];
			}
			else
			{
				$ElementParser['ElementDisabled'] = $TPL['list_disabled'];
			}
			$ElementParser['ElementDisableReason'] = end($BlockReason);
		}

		if($HideButton_Build)
		{
			$ElementParser['HideBuildButton'] = 'hide';
		}
		if($HideButton_Build OR $HideButton_QuickBuild)
		{
			$ElementParser['HideQuickBuildButton'] = 'hide';
		}

		if(!empty($ElementParser['AdditionalNfo']))
		{
			$ElementParser['AdditionalNfo'] = implode('', $ElementParser['AdditionalNfo']);
		}
		$ElementParser['ElementRequirementsHeadline'] = parsetemplate($ElementParser['ElementRequirementsHeadline'], $ElementParser);
		$StructuresList[] = parsetemplate($TPL['list_element'], $ElementParser);
		$InfoBoxes[] = parsetemplate($TPL['infobox_body'], $ElementParser);
	}

	if(!empty($LevelModifiers))
	{
		foreach($LevelModifiers as $ElementID => $Modifier)
		{
			$CurrentUser[$_Vars_GameElements[$ElementID]] += $Modifier;
		}
	}
	$CurrentPlanet['metal'] += $LockResources['metal'];
	$CurrentPlanet['crystal'] += $LockResources['crystal'];
	$CurrentPlanet['deuterium'] += $LockResources['deuterium'];

	// Create List
	$ThisRowIndex = 0;
	$InRowCount = 0;
	foreach($StructuresList as $ParsedData)
	{
		if($InRowCount == $ElementsPerRow)
		{
			$ParsedRows[($ThisRowIndex + 1)] = $TPL['list_breakrow'];
			$ThisRowIndex += 2;
			$InRowCount = 0;
		}

		$StructureRows[$ThisRowIndex]['Elements'] .= $ParsedData;
		$InRowCount += 1;
	}
	if($InRowCount < $ElementsPerRow)
	{
		$StructureRows[$ThisRowIndex]['Elements'] .= str_repeat($TPL['list_hidden'], ($ElementsPerRow - $InRowCount));
	}
	foreach($StructureRows as $Index => $Data)
	{
		$ParsedRows[$Index] = parsetemplate($TPL['list_row'], $Data);
	}
	ksort($ParsedRows, SORT_ASC);
	$Parse['Create_StructuresList'] = implode('', $ParsedRows);
	$Parse['Create_ElementsInfoBoxes'] = implode('', $InfoBoxes);
	if($ShowElementID > 0)
	{
		$Parse['Create_ShowElementOnStartup'] = $ShowElementID;
	}
	// End of - Parse all available technologies

	$Parse['Insert_SkinPath'] = $_SkinPath;
	$Parse['Insert_PlanetImg'] = $CurrentPlanet['image'];
	$Parse['Insert_PlanetType'] = $_Lang['PlanetType_'.$CurrentPlanet['planet_type']];
	$Parse['Insert_PlanetName'] = $CurrentPlanet['name'];
	$Parse['Insert_PlanetPos_Galaxy'] = $CurrentPlanet['galaxy'];
	$Parse['Insert_PlanetPos_System'] = $CurrentPlanet['system'];
	$Parse['Insert_PlanetPos_Planet'] = $CurrentPlanet['planet'];
	$Parse['Insert_Overview_LabLevel'] = $CurrentPlanet[$_Vars_GameElements[31]];
	$Parse['Insert_Overview_LabsConnected'] = prettyNumber($OtherLabs_ConnectedLabs);
	$Parse['Insert_Overview_TotalLabsCount'] = prettyNumber($OtherLabs_LabsCount);
	$Parse['Insert_Overview_LabPower'] = prettyNumber($OtherLabs_ConnectedLabsLevel);
	$Parse['Insert_Overview_LabPowerTotal'] = prettyNumber($OtherLabs_TotalLabsLevel);
	
	$Page = parsetemplate(gettemplate('buildings_compact_body_lab'), $Parse);

	display($Page, $_Lang['Research']);
}

?>