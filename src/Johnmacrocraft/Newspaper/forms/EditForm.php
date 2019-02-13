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

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Label;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class EditForm extends CustomForm {

	/** @var Config */
	private $info;
	/** @var BaseLang */
	private $lang;

	public function __construct(Config $info, BaseLang $lang) {
		$this->info = $info;
		$this->lang = $lang;

		parent::__construct($lang->translateString("gui.edit.title"), [
			new Label("Name", $info->get("name")),
			new Input("Description", $lang->translateString("gui.create.input.desc.name"), $lang->translateString("gui.create.input.desc.hint"), $info->get("description")),
			new Input("Member", $lang->translateString("gui.create.input.member.name"), $lang->translateString("gui.create.input.member.hint"), implode(", ", $info->get("member"))),
			new Input("Icon", $lang->translateString("gui.create.input.iconURL.name"), "https://en.touhouwiki.net/images/b/b4/Th16Aya.png", $info->get("icon")),
			new Input("Price_PerOne", $lang->translateString("gui.create.input.priceOne.name"), "0", $info->get("price")["perOne"]),
			new Input("Price_Subscription", $lang->translateString("gui.create.input.priceSub.name"), "0", $info->get("price")["subscriptions"])
		],
			function(Player $player, CustomFormResponse $data) : void {
				$profit = $this->info->get("profit"); //Copy profit value before setting data so that we don't lose it
				$this->info->setAll([
					"name" => $this->info->get("name"),
					"description" => $data->getString("Description"),
					"member" => (empty($member = $data->getString("Member")) ? [$player->getLowerCaseName()] : array_map("strtolower", explode(", ", $member))), //Players can retire from newspapers, so we don't check if their name is in the list
					"icon" => $data->getString("Icon")
				]);
				$this->info->setNested("price.perOne", (int) $data->getString("Price_PerOne"));
				$this->info->setNested("price.subscriptions", (int) $data->getString("Price_Subscription"));
				$this->info->set("profit", $profit);
				$this->info->save();
				$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.edit.success.edit"));
			}
		);
	}
}