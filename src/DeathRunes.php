<?php

declare(strict_types=1);

namespace PrograMistV1\DeathRunes;

use Exception;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\enchantment\EnchantingHelper;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use PrograMistV1\DeathRunes\commands\getRune;
use Symfony\Component\Filesystem\Path;

class DeathRunes extends PluginBase implements Listener{
    public const COMMAND_GETRUNE = "deathrunes.command.getrune";

    private static array $runesData = [];

    protected function onEnable(): void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $runes = new Config(Path::join($this->getDataFolder(), "runes.yml"), Config::YAML,
            [
                "armor" => [
                    "name" => "Armor Rune",
                    "item" => "book",
                    "lore" => '$black black ' . '$dark_blue dark_blue ' . '$dark_green dark_green ' . "\n" . '$dark_aqua dark_aqua ' . '$dark_red dark_red ' . '$dark_purple dark_purple ' . "\n" . '$gold gold ' . '$gray gray ' . '$dark_gray text ' . "\n" . '$blue text ' . '$green green ' . '$aqua aqua ' . "\n" . '$red red ' . '$light_purple light_purple ' . '$yellow yellow ' . "\n" . '$white white ' . '$minecoin_gold minecoin_gold ',
                    "items" => [
                        "diamond_helmet", "diamond_chestplate", "diamond_leggings", "diamond_boots"
                    ],
                    "chance" => 10,
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

                $rune["items"][] = StringToItemParser::getInstance()->parse($item) ?? throw new Exception("Unknown item: $item");
            }
            self::$runesData[strtolower($runeId)] = $rune;
        }

        $this->getServer()->getCommandMap()->register("deathrunes", new getRune($this));
    }

    private function str_replace(string $text): string{
        return str_replace(['$black', '$dark_blue', '$dark_green', '$dark_aqua', '$dark_red', '$dark_purple', '$gold', '$gray', '$dark_gray', '$blue', '$green', '$aqua', '$red', '$light_purple', '$yellow', '$white', '$minecoin_gold',], TextFormat::COLORS, $text);
    }

    public function onBlockBreak(BlockBreakEvent $event): void{
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if (mt_rand(1, 100) <= $this->getRuneChance()) {
            $runeId = $this->getRandomRuneId();
            $rune = self::getRune($runeId);

            $player->getInventory()->addItem($rune);

            $player->sendMessage("You found a " . self::$runesData[$runeId]["name"] . "!");
            $this->playSound($player, "random.explode");
        }
    }

    private function getRuneChance(): int{
        $defaultChance = 10;
        return self::$runesData[array_key_first(self::$runesData)]["chance"] ?? $defaultChance;
    }

    private function getRandomRuneId(): string{
        $availableRunes = array_keys(self::$runesData);
        $randomIndex = array_rand($availableRunes);
        return $availableRunes[$randomIndex];
    }

    public function onInventoryAction(InventoryTransactionEvent $event): void{
        $actions = $event->getTransaction()->getActions();
        $player = $event->getTransaction()->getSource();
        if(count($actions) != 2){
            return;
        }

        $runeItem = null;
        $runeSlot = null;
        $runeInventory = null;

        $targetItem = null;
        $targetSlot = null;
        $targetInventory = null;

        foreach($actions as $action){
            if($action instanceof SlotChangeAction){
                $eventTarget = $action->getTargetItem();
                $eventSource = $action->getSourceItem();
                if($eventSource->isNull() || $eventTarget->isNull()){
                    return;
                }
                if(!self::isRune($eventSource) && !self::isRune($eventTarget)){
                    return;
                }
                $eventItem = $action->getInventory()->getItem($action->getSlot());
                if(self::isRune($eventItem)){
                    $runeItem = $eventItem;
                    $runeSlot = $action->getSlot();
                    $runeInventory = $action->getInventory();
                }else{
                    $targetItem = $eventItem;
                    $targetSlot = $action->getSlot();
                    $targetInventory = $action->getInventory();
                }
            }
        }

        if(!$targetItem->keepOnDeath()){
            foreach(self::$runesData[$runeItem->getNamedTag()->getString("rune")]["items"] as $item){
                if($item->equals($targetItem, false, false)){
                    $runeItem->pop();
                    $targetItem->setKeepOnDeath(true);

                    $runeInventory->setItem($runeSlot, $runeItem);
                    $targetInventory->setItem($targetSlot, $targetItem);

                    $event->cancel();
                    $this->playSound($player, "note.bell");
                    return;
                }
            }
        }
        $this->playSound($player, "note.bass");
    }

    private function playSound(Player $player, string $sound): void{
        $pos = $player->getPosition();
        $packet = PlaySoundPacket::create($sound, $pos->getX(), $pos->getY(), $pos->getZ(), 150, 1);
        $player->getNetworkSession()->sendDataPacket($packet);
    }

    public function onDeath(PlayerRespawnEvent $event): void{
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
            if($armorInventory->isSlotEmpty($i)){
                continue;
            }
            $item = $armorInventory->getItem($i);
            if($item->keepOnDeath()){
                $item->setKeepOnDeath(false);
                $armorInventory->setItem($i, $item);
            }
        }
    }

    /**
     * @return array<string, array<string, string|array<string>>>
     */
    public static function getRunes(): array{
        return self::$runesData;
    }

    public static function getRune(string $runeId): Item{
        /** @var Item $item */
        $item = self::$runesData[$runeId]["item"];
        $enchantment = StringToEnchantmentParser::getInstance()->parse("fortune");
        $item = EnchantingHelper::enchantItem($item, [new EnchantmentInstance($enchantment)]);
        $item->setCustomName(self::$runesData[$runeId]["name"]);
        $tags = $item->getNamedTag();
        $tags->setString("rune", $runeId);
        $item->setNamedTag($tags);
        $item->setLore([TextFormat::RESET . self::$runesData[$runeId]["lore"]]);
        return $item;
    }

    public static function isRune(Item $item): bool{
        return $item->getNamedTag()->getString("rune", "null") !== "null";
    }
}
