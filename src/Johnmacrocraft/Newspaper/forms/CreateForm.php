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
use pocketmine\form\element\Input;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class CreateForm extends CustomForm {

	/** @var BaseLang */
	private $lang;

	public function __construct(BaseLang $lang) {
		$this->lang = $lang;
		parent::__construct($lang->translateString("gui.create.title"),
			[new Input("Name", $lang->translateString("gui.create.input.name.name"), $lang->translateString("gui.create.input.name.hint")),
				new Input("Description", $lang->translateString("gui.create.input.desc.name"), $lang->translateString("gui.create.input.desc.hint")),
				new Input("Member", $lang->translateString("gui.create.input.member.name"), $lang->translateString("gui.create.input.member.hint")),
				new Input("Icon", $lang->translateString("gui.create.input.iconURL.name"), "https://en.touhouwiki.net/images/b/b4/Th16Aya.png"),
				new Input("Price_PerOne", $lang->translateString("gui.create.input.priceOne.name"), "0"),
				new Input("Price_Subscription", $lang->translateString("gui.create.input.priceSub.name"), "0")
			]
		);
	}

	public function onSubmit(Player $player, CustomFormResponse $data) : void {
		if(is_dir(Newspaper::getPlugin()->getNewspaperFolder() . strtolower($newspaper = $data->getString("Name")))) {
			$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.create.error.alreadyExists"));
		} else {
			if(strpbrk($newspaper, "\\/:*?\"<>|") === FALSE && !empty($newspaper)) { //We don't want people trying to use invalid characters on Windows system, or access parent directories
				Newspaper::getPlugin()->createNewspaper($newspaper,
					$data->getString("Description"),
					(empty($member = $data->getString("Member")) ? [$player->getName()] : explode(", ", $member)),
					$data->getString("Icon"),
					(int) $data->getString("Price_PerOne"),
					(int) $data->getString("Price_Subscription")
				);

				$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.create.success.create"));
			} else {
				$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.create.error.invalidName"));
			}
		}
	}
}
