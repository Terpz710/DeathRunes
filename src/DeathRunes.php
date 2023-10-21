<?php

declare(strict_types=1);

namespace PrograMistV1\DeathRunes;

use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\enchantment\EnchantingHelper;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use PrograMistV1\DeathRunes\commands\getRune;
use Symfony\Component\Filesystem\Path;

class DeathRunes extends PluginBase implements Listener{
    /**
     * @var array<string, array<string, string|array<string>>>
     */
    private static array $runesData = [];
    private static array $runes = [];

    protected function onEnable() : void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $runes = new Config(Path::join($this->getDataFolder(), "runes.yml"), Config::YAML,
            [
                "armor" => [
                    "name" => "Armor Rune",
                    "item" => "book",
                    "lore" => '$black black '.'$dark_blue dark_blue '.'$dark_green dark_green '."\n".'$dark_aqua dark_aqua '.'$dark_red dark_red '.'$dark_purple dark_purple '."\n".'$gold gold '.'$gray gray '.'$dark_gray text '."\n".'$blue text '.'$green green '.'$aqua aqua '."\n".'$red red '.'$light_purple light_purple '.'$yellow yellow '."\n".'$white white '.'$minecoin_gold minecoin_gold ',
                    "items" => [
                        "diamond_helmet", "diamond_chestplate"
                    ]
                ]
            ]
        );
        foreach($runes->getAll() as $runeId => $rune){
            $rune["name"] = $this->str_replace($rune["name"]);
            $rune["lore"] = $this->str_replace($rune["lore"]);
            $item = strtoupper($rune["item"]);
            $rune["item"] = VanillaItems::$item();
            foreach($rune["items"] as $index => $item){
                unset($rune["items"][$index]);
                $rune["items"][] = VanillaItems::$item();
            }
            self::$runesData[strtolower($runeId)] = $rune;
            self::$runes[$rune["name"]] = $rune["items"];
        }
        $this->getServer()->getCommandMap()->register("runes", new getRune());
    }

    private function str_replace(string $text) : string{
        return str_replace(['$black', '$dark_blue', '$dark_green', '$dark_aqua', '$dark_red', '$dark_purple', '$gold', '$gray', '$dark_gray', '$blue', '$green', '$aqua', '$red', '$light_purple', '$yellow', '$white', '$minecoin_gold',], TextFormat::COLORS, $text);
    }

    /**
     * @return array<string, array<string, string|array<string>>>
     */
    public static function getRunes() : array{
        return self::$runesData;
    }

    public function onInventoryAction(InventoryTransactionEvent $event) : void{
        $actions = $event->getTransaction()->getActions();
        $player = $event->getTransaction()->getSource();
        foreach($actions as $action){
            if($action instanceof SlotChangeAction){
                $target = $action->getTargetItem();
                if(in_array($target->getCustomName(), array_keys(self::$runes)) && !$action->getInventory() instanceof PlayerCursorInventory){
                    $source = $action->getSourceItem();
                    /** @var Item $item */
                    foreach(self::$runes[$target->getCustomName()] as $item){
                        if($item->equals($source, false, false) && !$source->keepOnDeath()){
                            $event->cancel();
                            $target->pop();
                            $source->setKeepOnDeath(true);
                            $player->getCursorInventory()->setItem(0, $target);
                            $player->getInventory()->setItem($action->getSlot(), $source);
                        }
                    }
                }
            }
        }
    }

    public function onDeath(PlayerRespawnEvent $event) : void{
        $player = $event->getPlayer();
        $inventory = $player->getInventory();
        $armorInventory = $player->getArmorInventory();
        for($i = 0, $size = $inventory->getSize(); $i < $size; ++$i){
            if($inventory->isSlotEmpty($i)){
                continue;
            }
            $item = $inventory->getItem($i);
            if($item->keepOnDeath()){
                $item->setKeepOnDeath(false);
                $inventory->setItem($i, $item);
            }
        }
        for($i = 0, $size = $armorInventory->getSize(); $i < $size; ++$i){
            if($inventory->isSlotEmpty($i)){
                continue;
            }
            $item = $armorInventory->getItem($i);
            if($item->keepOnDeath()){
                $item->setKeepOnDeath(false);
                $inventory->setItem($i, $item);
            }
        }
    }

    public static function getRune(string $runeId) : Item{
        /** @var Item $item */
        $item = self::$runesData[$runeId]["item"];
        $enchantment = StringToEnchantmentParser::getInstance()->parse("fortune");
        $item = EnchantingHelper::enchantItem($item, [new EnchantmentInstance($enchantment)]);
        $item->setCustomName(self::$runesData[$runeId]["name"]);
        $item->setLore([TextFormat::RESET.self::$runesData[$runeId]["lore"]]);
        return $item;
    }
}