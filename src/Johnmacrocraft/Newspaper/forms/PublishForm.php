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
use pocketmine\form\element\Label;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\WrittenBook;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class PublishForm extends CustomForm {

	/** @var string */
	public $name;
	/** @var BaseLang */
	private $lang;

	public function __construct(string $name, BaseLang $lang) {
		$this->lang = $lang;
		parent::__construct($lang->translateString("gui.publish.title"),
			[new Label("Notice", TextFormat::GOLD . $lang->translateString("gui.publish.label")),
				new Input("Name", $lang->translateString("gui.publish.input.name.name"), "Bad Adler32!!"),
				new Input("Description", $lang->translateString("gui.publish.input.desc.name"), $lang->translateString("gui.publish.input.desc.hint")),
				new Input("Author", $lang->translateString("gui.publish.input.author.name"), $lang->translateString("gui.publish.input.author.name"))
			]
		);
		$this->name = $name;
	}

	public function onSubmit(Player $player, CustomFormResponse $data) : void {
		$getItem = $player->getInventory()->getItemInHand();

		if(($getId = $getItem->getId()) === ItemIds::WRITABLE_BOOK || $getId === ItemIds::WRITTEN_BOOK) {
			if(is_file($newspaper = Newspaper::getPlugin()->getNewspaperFolder() . $this->name . "/newspaper/" . (strtolower($name = empty($data->getString("Name")) ? $getItem->getTitle() : $data->getString("Name"))) . ".yml")) {
				$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.create.error.alreadyExists"));
			} else {
				if(strpbrk($name, "\\/:*?\"<>|") === FALSE && !empty($name)) { //We don't want people trying to use invalid characters on Windows system, access parent directories, or empty names
					$newspaperInfo = new Config($newspaper,
						Config::YAML,
						["name" => $name,
						"description" => $data->getString("Description"),
						"author" => (empty($author = $data->getString("Author")) ? ($getId === ItemIds::WRITTEN_BOOK ? $getItem->getAuthor() : $player->getName()) : $author),
						"generation" => $getItem === ItemIds::WRITTEN_BOOK ?: WrittenBook::GENERATION_ORIGINAL]
					);
					$newspaperData = new Config(Newspaper::getPlugin()->getNewspaperFolder() . $this->name . "/newspaper/" . strtolower($name) . ".dat", Config::SERIALIZED, $getItem->getPages());

					Newspaper::getPlugin()->cleanExpired();
					foreach(glob(Newspaper::getPlugin()->getPlayersFolder() . "*.yml") as $playerDataPath) {
						$playerData = new Config($playerDataPath, Config::YAML);

						if(isset($playerData->getAll()["subscriptions"][$this->name])) {
							if(($subscriber = Server::getInstance()->getPlayer(pathinfo($playerDataPath, PATHINFO_FILENAME)))->isOnline()) {
								$item = ItemFactory::fromString(ItemIds::WRITTEN_BOOK);
								$item->setCount(1);
								$item->setPages(($newspaperData->getAll()));
								$item->setTitle($newspaperInfo->get("name"));
								$item->setAuthor($newspaperInfo->get("author"));
								$item->setGeneration($newspaperInfo->get("generation"));

								if($subscriber->getInventory()->canAddItem($item)) {
									$player->getInventory()->addItem($item);
									break;
								} else {
									$player->sendMessage(TextFormat::GOLD . $this->lang->translateString("gui.publish.sub.info"));
								}

							}
							$key = "subscriptions." . $this->name . ".queue";
							$queue = $playerData->getNested($key);
							$queue[] = strtolower($name);
							$playerData->setNested($key, $queue);
							$playerData->save();
						}
					}

					$player->sendMessage(TextFormat::GREEN . $this->lang->translateString("gui.publish.success.publish"));
				} else {
					$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.create.error.invalidName"));
				}
			}
		} else {
			$player->sendMessage(TextFormat::RED . $this->lang->translateString("gui.publish.error.notBook"));
		}
	}
}
