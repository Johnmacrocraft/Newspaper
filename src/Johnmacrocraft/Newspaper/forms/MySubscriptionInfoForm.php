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
use pocketmine\utils\TextFormat;

class MySubscriptionInfoForm extends MenuForm {

	/** @var string */
	private $newspaper;
	/** @var BaseLang */
	private $lang;

	public function __construct(string $player, string $newspaper, BaseLang $lang) {
		$this->lang = $lang;

		parent::__construct($this->lang->translateString("gui.subinfo.title"),
			$this->lang->translateString("gui.subinfo.label.expiresAt", [Newspaper::getPlugin()->getSubscription($player, $newspaper)["subscribeUntil"]]) . TextFormat::EOL .
			$this->lang->translateString(Newspaper::getPlugin()->getPlayerData($player)->get("autorenew") ?
				"gui.subinfo.label.autorenew.enabled" :
				"gui.subinfo.label.autorenew.disabled"
			),
			[new MenuOption($this->lang->translateString("gui.subinfo.button.unsub"))],
			function(Player $player) : void {
				if(!Newspaper::getPlugin()->badPerm($player, "gui.subscriptions.unsubscribe", "gui.subinfo.perm.unsub")) {
					Newspaper::getPlugin()->removeSubscription($player->getName(), $this->newspaper);
					$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.subinfo.success.unsub"));
				}
			}
		);

		$this->newspaper = $newspaper;
	}
}
