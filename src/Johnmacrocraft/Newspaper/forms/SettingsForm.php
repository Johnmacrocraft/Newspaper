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

class SettingsForm extends MenuForm {

	/** @var BaseLang */
	private $lang;

	public function __construct(BaseLang $lang) {
		$this->lang = $lang;
		parent::__construct($lang->translateString("gui.settings.title"), $lang->translateString("gui.settings.label"),
			[new MenuOption($lang->translateString("gui.settings.button.settings")), new MenuOption($lang->translateString("gui.sub.title"))],
			function(Player $player, int $selectedOption) : void {
				if($selectedOption === 0) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.settings.settings", "gui.settings.perm.settings")) {
						$player->sendForm(new SettingsListForm($player->getName(), $this->lang));
					}
				} elseif($selectedOption === 1) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.subscriptions", "gui.settings.perm.subscriptions")) {
						$player->sendForm(new MySubscriptionsForm($player->getName(), $this->lang));
					}
				}
			}
		);
	}
}
