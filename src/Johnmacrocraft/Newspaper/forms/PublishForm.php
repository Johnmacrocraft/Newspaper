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
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Label;
use pocketmine\item\ItemIds;
use pocketmine\item\WrittenBook;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class PublishForm extends CustomForm {

	/** @var string */
	public $mainNewspaper;
	/** @var BaseLang */
	private $lang;

	public function __construct(string $mainNewspaper, BaseLang $lang) {
		$this->lang = $lang;

		parent::__construct($lang->translateString("gui.publish.title"), [
			new Label("Notice", TextFormat::GOLD . $lang->translateString("gui.publish.label")),
			new Input("Name", $lang->translateString("gui.publish.input.name.name"), "Bad Adler32!!"),
			new Input("Description", $lang->translateString("gui.publish.input.desc.name"), $lang->translateString("gui.publish.input.desc.hint")),
			new Input("Author", $lang->translateString("gui.publish.input.author.name"), $lang->translateString("gui.publish.input.author.name"))
		],
			function(Player $player, CustomFormResponse $data) : void {
				$heldItem = $player->getInventory()->getItemInHand();
				$itemId = $heldItem->getId();

				if($itemId === ItemIds::WRITABLE_BOOK || $itemId === ItemIds::WRITTEN_BOOK) {
					if(is_file(Newspaper::getPlugin()->getNewspaperFolder() . $this->mainNewspaper . "/newspaper/" . (strtolower($newspaper = empty($data->getString("Name")) ? $heldItem->getTitle() : $data->getString("Name"))) . ".yml")) {
						$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.create.error.alreadyExists"));
					} else {
						if(strpbrk($newspaper, "\\/:*?\"<>|") === FALSE && !empty($newspaper)) { //We don't want people trying to use invalid characters on Windows system, access parent directories, or empty names
							$author = $data->getString("Author");
							Newspaper::getPlugin()->publishNewspaper($this->mainNewspaper,
								$newspaper,
								$data->getString("Description"),
								(empty($author) ? ($itemId === ItemIds::WRITTEN_BOOK ? $heldItem->getAuthor() : $player->getName()) : $author), //No need to use a lowercased name on here
								$heldItem === ItemIds::WRITTEN_BOOK ?: WrittenBook::GENERATION_ORIGINAL,
								$heldItem->getPages()
							);

							$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.publish.success.publish"));
						} else {
							$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.create.error.invalidName"));
						}
					}
				} else {
					$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.publish.error.notBook"));
				}
			}
		);
		$this->mainNewspaper = $mainNewspaper;
	}
}
