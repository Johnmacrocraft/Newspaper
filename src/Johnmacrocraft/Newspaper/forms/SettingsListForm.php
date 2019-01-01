<?php

/*
 *
 * Newspaper
 *
 * Copyright © 2018-2019 Johnmacrocraft
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace Johnmacrocraft\Newspaper\forms;

use Johnmacrocraft\Newspaper\Newspaper;
use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Toggle;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class SettingsListForm extends CustomForm {

	/** @var BaseLang */
	private $lang;
	/** @var array */
	private $langList;
	/** @var Config */
	private $playerData;

	public function __construct(string $playerName, BaseLang $lang) {
		$this->lang = $lang;
		foreach(Newspaper::getPlugin()->getLanguageList() as $langPath) {
			$this->langList[] = pathinfo($langPath, PATHINFO_FILENAME);
		}
		$this->playerData = Newspaper::getPlugin()->getPlayerData($playerName);
		parent::__construct($lang->translateString("gui.settings.title"),
			[new Dropdown("Language", $lang->translateString("gui.settingslist.dropdown.lang.name"), $this->langList, array_search($this->playerData->get("lang"), $this->langList)),
				new Toggle("Auto_Renew", $lang->translateString("gui.settingslist.toggle.autorenew.name"), $this->playerData->get("autorenew"))
			],
			function(Player $player, CustomFormResponse $data) : void {
				$this->playerData->set("lang", $this->langList[$data->getInt("Language")]);
				$this->playerData->set("autorenew", $data->getBool("Auto_Renew"));
				$this->playerData->save();
				$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.settingslist.success.set"));
			}
		);
	}
}
