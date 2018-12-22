<?php

/*
 *
 * Newspaper
 *
 * Copyright © 2018 Johnmacrocraft
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

class CreateTypeForm extends MenuForm {

	/** @var BaseLang */
	private $lang;

	public function __construct(BaseLang $lang) {
		$this->lang = $lang;
		parent::__construct($lang->translateString("gui.create.title"),
			$lang->translateString("gui.createtype.label"),
			[new MenuOption($lang->translateString("gui.createtype.button.new")), new MenuOption($lang->translateString("gui.createtype.button.publish"))],
			function(Player $player, int $selectedOption) : void {
				if($selectedOption === 0) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.create.new", "gui.createtype.perm.new")) {
						$player->sendForm(new CreateForm($this->lang));
					}
				} elseif($selectedOption === 1) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.create.publish", "gui.createtype.perm.publish")) {
						$player->sendForm(new PublishItemForm($player->getLowerCaseName(), $this->lang));
					}
				}
			}
		);
	}
}