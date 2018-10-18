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
use pocketmine\form\CustomForm;
use pocketmine\form\CustomFormResponse;
use pocketmine\form\element\Label;
use pocketmine\form\element\Toggle;
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
		if(empty($newspapers = Newspaper::getPlugin()->getAllPublishedNewspapers($info->get("name")))) {
			$options[] = new Label("No_Newspapers", $lang->translateString("gui.buyitems.label.noItems"));
		} else {
			foreach($newspapers as $newspaper) {
				$options[] = new Toggle($name = (new Config($newspaper, Config::YAML))->get("name"), $name);
				$this->newspapers[] = $name;
			}
		}
		parent::__construct($lang->translateString("gui.buyitems.title"), $options);
	}

	public function onSubmit(Player $player, CustomFormResponse $data) : void {
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
			if(($API = Newspaper::getPlugin()->getEconomyAPI())->reduceMoney($player, $price, true, "Newspaper") === $API::RET_INVALID) {
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
			$item->setPages(($newspaperData = Newspaper::getPlugin()->getPublishedNewspaper($this->info->get("name"), $newspaper))[1]->getAll());
			$item->setTitle($newspaperData[0]->get("name"));
			$item->setAuthor($newspaperData[0]->get("author"));
			$item->setGeneration($newspaperData[0]->get("generation"));
			$player->getInventory()->addItem($item);
		}

		$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.buyitems.success.buy", [$this->info->get("name"), Newspaper::getPlugin()->getEconomyAPI()->getMonetaryUnit() . $price]));
	}
}