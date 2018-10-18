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
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class BuyInfoForm extends MenuForm {

	/** @var Config */
	private $info;
	/** @var BaseLang */
	private $lang;

	public function __construct(Config $info, BaseLang $lang) {
		$this->info = $info;
		$this->lang = $lang;
		parent::__construct($lang->translateString("gui.buyinfo.title", [$info->get("name")]),
			$lang->translateString("gui.buyinfo.label.desc", [$info->get("description")]) . TextFormat::EOL .
			$lang->translateString("gui.buyinfo.label.member", [implode(", ", $info->get("member"))]) . TextFormat::EOL .
			$lang->translateString("gui.buyinfo.label.priceOne", [$info->get("price")["perOne"]]) . TextFormat::EOL .
			$lang->translateString("gui.buyinfo.label.priceSub", [$info->get("price")["subscriptions"]]),
			[new MenuOption($lang->translateString("gui.buyinfo.button.buy", [($monetary = (Newspaper::getPlugin()->canBuyNewspapers() ? Newspaper::getPlugin()->getEconomyAPI()->getMonetaryUnit() : null)) . $info->get("price")["perOne"]])),
				new MenuOption($lang->translateString("gui.buyinfo.button.subscribe", [$monetary . $info->get("price")["subscriptions"]]))
			]
		);
	}

	public function onSubmit(Player $player, int $selectedOption) : void {
		if($selectedOption === 0) {
			if(!Newspaper::getPlugin()->badPerm($player, "gui.buy.one", "gui.main.perm.buy")) {
				$player->sendForm(new BuyItemsListForm($this->info, $this->lang));
			}
		} elseif($selectedOption === 1) {
			if(Newspaper::getPlugin()->badPerm($player, "gui.buy.subscribe", "gui.buyinfo.perm.subscribe")) {
				return;
			}

			$name = strtolower($this->info->get("name"));

			if(isset(Newspaper::getPlugin()->getPlayerData($player->getName())->getAll()["subscriptions"][$name])) {
				$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.buyinfo.error.alreadySubscribe"));
				return;
			}

			$price = $this->info->get("price")["subscriptions"];

			if(Newspaper::getPlugin()->canBuyNewspapers()) {
				if(($API = Newspaper::getPlugin()->getEconomyAPI())->reduceMoney($player, $price, true, "Newspaper") === $API::RET_INVALID) {
					$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.buyinfo.error.noMoney", [Newspaper::getPlugin()->getEconomyAPI()->getMonetaryUnit() . ($price - $API->myMoney($player))]));
					return;
				}
			} else {
				$player->sendMessage(TextFormat::GOLD . $this->lang->translateString("gui.buy.info.noFee"));
			}

			$this->info->set("profit", $this->info->get("profit") + $price);
			$this->info->save();
			Newspaper::getPlugin()->setSubscription($player->getName(), $name);
			$noSpace = false;
			$queue = [];

			if($player->getInventory()->getSize() - count($player->getInventory()->getContents()) < count($newspapers = Newspaper::getPlugin()->getAllPublishedNewspapers($name))) {
				$noSpace = true;
				$player->sendMessage(TextFormat::GOLD . $this->lang->translateString("gui.buyinfo.info.invNoSpace"));
			}

			foreach($newspapers as $newspaper) {
				$newspaperData = Newspaper::getPlugin()->getPublishedNewspaper($name, pathinfo($newspaper, PATHINFO_FILENAME));

				if($noSpace) {
					$queue[] = strtolower($newspaperData[0]->get("name"));
				} else {
					$item = ItemFactory::fromString(ItemIds::WRITTEN_BOOK);
					$item->setCount(1);
					$item->setPages($newspaperData[1]->getAll());
					$item->setTitle($newspaperData[0]->get("name"));
					$item->setAuthor($newspaperData[0]->get("author"));
					$item->setGeneration($newspaperData[0]->get("generation"));
					$player->getInventory()->addItem($item);
				}
			}

			($subscriptions = Newspaper::getPlugin()->getPlayerData($player->getName()))->setNested("subscriptions." . $name . ".queue", $queue);
			$subscriptions->save();
			$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.buyinfo.success.subscribe", [$this->info->get("name")]));
		}
	}
}