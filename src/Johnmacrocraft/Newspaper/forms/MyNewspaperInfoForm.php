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
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class MyNewspaperInfoForm extends MenuForm {

	/** @var Config */
	private $info;
	/** @var BaseLang */
	private $lang;

	public function __construct(Config $info, BaseLang $lang) {
		$this->info = $info;
		$this->lang = $lang;
		parent::__construct($lang->translateString("gui.newspaperInfo.title", [$info->get("name")]),
			$lang->translateString("gui.newspaperInfo.label", [$info->get("profit")]),
			[new MenuOption($lang->translateString("gui.newspaperInfo.button.edit")),
				new MenuOption($lang->translateString("gui.newspaperInfo.button.getProfit"))
			],
			function(Player $player, int $selectedOption) : void {
				if($selectedOption === 0) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.manage.edit", "gui.newspaperinfo.perm.edit")) {
						$player->sendForm(new EditForm($this->info, $this->lang));
					}
				} elseif($selectedOption === 1) {
					if(!Newspaper::getPlugin()->badPerm($player, "gui.manage.getProfit", "gui.newspaperinfo.perm.getProfit")) {
						if(Newspaper::getPlugin()->canBuyNewspapers()) {
							Newspaper::getPlugin()->getEconomyAPI()->addMoney($player, $this->info->get("profit"));
							$this->info->set("profit", 0);
							$this->info->save();
							$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.newspaperInfo.success.getProfit"));
						} else {
							$player->sendMessage(TextFormat::GOLD . $this->lang->translateString("gui.buy.info.noFee"));
						}
					}
				}
			}
		);
	}
}
