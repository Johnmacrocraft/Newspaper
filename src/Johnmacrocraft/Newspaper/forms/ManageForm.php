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
use pocketmine\utils\Config;

class ManageForm extends MenuForm {

	/** @var BaseLang */
	private $lang;

	public function __construct(string $playerName, BaseLang $lang) {
		$this->lang = $lang;
		foreach(Newspaper::getPlugin()->getAllNewspaperInfo() as $info) {
			if(in_array($playerName, ($config = new Config($info, Config::YAML))->get("member"))) {
				$options[] = new MenuOption($config->get("name"));
			}
		}
		parent::__construct($lang->translateString("gui.manage.title"), $this->lang->translateString("gui.manage.label"), $options,
			function(Player $player, int $selectedOption) : void {
				$player->sendForm(new MyNewspaperInfoForm(Newspaper::getPlugin()->getNewspaperInfo($this->getOption($selectedOption)->getText()), $this->lang));
			}
		);
	}
}