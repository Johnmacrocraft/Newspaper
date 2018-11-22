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
use pocketmine\form\MenuForm;
use pocketmine\form\MenuOption;
use pocketmine\lang\BaseLang;
use pocketmine\Player;

class MySubscriptionsForm extends MenuForm {

	/** @var BaseLang */
	private $lang;

	public function __construct(string $name, BaseLang $lang) {
		$this->lang = $lang;
		if(empty($subscriptions = Newspaper::getPlugin()->getPlayerData($name)->get("subscriptions"))) {
			$options = [];
		} else {
			foreach (array_keys($subscriptions) as $subscribedItem) {
				$options[] = new MenuOption($subscribedItem);
			}
		}
		parent::__construct($lang->translateString("gui.sub.title"), $this->lang->translateString("gui.sub.label"), $options);
	}

	public function onSubmit(Player $player, int $selectedOption) : void {
	    $player->sendForm(new MySubscriptionInfoForm($player->getName(), strtolower($this->getOption($selectedOption)->getText()), $this->lang));
	}
}
