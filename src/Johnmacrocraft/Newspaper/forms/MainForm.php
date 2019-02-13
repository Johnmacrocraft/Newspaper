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
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\lang\BaseLang;
use pocketmine\Player;

class MainForm extends MenuForm {

	/** @var BaseLang */
	private $lang;

	public function __construct(string $playerName) {
		$this->lang = Newspaper::getPlugin()->getLanguage(Newspaper::getPlugin()->getPlayerData($playerName)->get("lang"));

		parent::__construct("Newspaper",
			$this->lang->translateString("gui.main.label"), [
				new MenuOption($this->lang->translateString("gui.main.button.create")),
				new MenuOption($this->lang->translateString("gui.main.button.buy")),
				new MenuOption($this->lang->translateString("gui.main.button.manage")),
				new MenuOption($this->lang->translateString("gui.main.button.settings"))
			],
			function(Player $player, int $selectedOption) : void {
				if($selectedOption === 0) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.create", "gui.main.perm.create")) {
						$player->sendForm(new CreateTypeForm($this->lang));
					}
				} elseif($selectedOption === 1) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.buy", "gui.main.perm.buy")) {
						$player->sendForm(new ItemListForm($this->lang));
					}
				} elseif($selectedOption === 2) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.manage", "gui.main.perm.manage")) {
						$player->sendForm(new ManageForm($player->getLowerCaseName(), $this->lang));
					}
				} elseif($selectedOption === 3) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.settings", "gui.main.perm.settings")) {
						$player->sendForm(new SettingsForm($this->lang));
					}
				}
			}
		);
	}
}