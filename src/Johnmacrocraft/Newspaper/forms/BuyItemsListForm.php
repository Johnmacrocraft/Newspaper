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
use dktapps\pmforms\element\Label;
use dktapps\pmforms\element\Toggle;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class BuyItemsListForm extends CustomForm {

	/** @var Config */
	private $info;
	/** @var BaseLang */
	private $lang;
	/** @var array */
	private $newspapers = [];

	public function __construct(Config $info, BaseLang $lang) {
		$this->info = $info;
		$this->lang = $lang;

		$newspapers = Newspaper::getPlugin()->getAllPublished($info->get("name"));

		if(empty($newspapers)) {
			$options[] = new Label("No_Newspapers", $lang->translateString("gui.buyitems.label.noItems"));
		} else {
			foreach($newspapers as $newspaper) {
				$newspaperName = (new Config($newspaper, Config::YAML))->get("name");
				$options[] = new Toggle($newspaperName, $newspaperName);
				$this->newspapers[] = $newspaperName;
			}
		}
		parent::__construct($lang->translateString("gui.buyitems.title"), $options,
			function(Player $player, CustomFormResponse $data) : void {
				$selected = [];

				foreach($this->newspapers as $newspaper) {
					if($data->getBool($newspaper)) {
						$selected[] = $newspaper;
					}
				}

				$price = $this->info->get("price")["perOne"] * count($selected);

				if(empty($selected)) {
					$player->sendMessage(TextFormat::GOLD . $this->lang->translateString("gui.buyitems.info.cancelPurchase"));
					return;
				}

				if($player->getInventory()->getSize() - count($player->getInventory()->getContents()) < count($selected)) {
					$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.buyitems.error.invNoSpace"));
					return;
				}

				if(Newspaper::getPlugin()->canBuyNewspapers()) {
					$API = Newspaper::getPlugin()->getEconomyAPI();
					if($API->reduceMoney($player, $price, true, "Newspaper") === $API::RET_INVALID) {
						$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.buyitems.error.noMoney", [Newspaper::getPlugin()->getEconomyAPI()->getMonetaryUnit() . ($price - $API->myMoney($player))]));
						return;
					}
				} else {
					$player->sendMessage(TextFormat::GOLD . $this->lang->translateString("gui.buy.info.noFee"));
				}

				$this->info->set("profit", $this->info->get("profit") + $price);
				$this->info->save();

				foreach($selected as $newspaper) {
					$item = ItemFactory::fromString(ItemIds::WRITTEN_BOOK);
					$item->setCount(1);
					$newspaperInfo = Newspaper::getPlugin()->getPublishedInfo($this->info->get("name"), $newspaper);
					$newspaperPages = Newspaper::getPlugin()->getPublishedPages($this->info->get("name"), $newspaper);
					$item->setPages($newspaperPages->getAll());
					$item->setTitle($newspaperInfo->get("name"));
					$item->setAuthor($newspaperInfo->get("author"));
					$item->setGeneration($newspaperInfo->get("generation"));
					$player->getInventory()->addItem($item);
				}

				$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.buyitems.success.buy", [$this->info->get("name"), Newspaper::getPlugin()->getEconomyAPI()->getMonetaryUnit() . $price]));
			}
		);
	}
}
