<?php

namespace xenialdan\mobloot;

use InvalidArgumentException;
use pocketmine\entity\Creature;
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\plugin\PluginException;
use pocketmine\Server;
use RuntimeException;

class LootGenerator
{
    private $lootname = "empty";
    private $entity;
    private $lootFile = [];

    /**
     * LootGenerator constructor.
     * @param $lootname
     * @param Creature|null $entity
     * @throws InvalidArgumentException
     */
    public function __construct($lootname = "loot_tables/empty.json", ?Creature $entity = null)
    {
        $lootname = str_replace(".json", "", $lootname);
        if (!array_key_exists($lootname, API::$loottables)) throw new InvalidArgumentException("LootTable " . $lootname . " not found" . (is_null($entity) ? "" : " for entity of type " . $entity->getName()));
        $this->lootname = $lootname;
        $this->lootFile = API::$loottables[$this->lootname];
        $this->entity = $entity;
    }

    /**
     * @return Item[]
     * @throws InvalidArgumentException
     * @throws PluginException
     * @throws RuntimeException
     */
    public function getRandomLoot(): array
    {
        $items = [];
        if (!isset($this->lootFile["pools"])) {
            return $items;
        }
        foreach ($this->lootFile["pools"] as $rolls) {//TODO sub-pools, see armor chain etc
            //TODO roll conditions.. :(
            //TODO i saw "tiers" and have no idea what these do
            $array = [];
            $maxrolls = $rolls["rolls"];//TODO: $rolls["conditions"]
            while ($maxrolls > 0) {
                $maxrolls--;
                //TODO debug this roll condition check
                if (isset($rolls["conditions"])) {
                    if (!API::checkConditions($this->entity, $rolls["conditions"])) continue;
                }
                //
                foreach ($rolls["entries"] as $index => $entries) {
                    $array[] = $entries["weight"] ?? 1;
                }
            }
            if (count($array) > 1)
                $val = $rolls["entries"][API::getRandomWeightedElement($array)] ?? [];
            else
                $val = $rolls["entries"][0] ?? [];
            //typecheck
            if ($val["type"] == "loot_table") {
                $loottable = new LootGenerator($val["name"], $this->entity);
                $items = array_merge($items, $loottable->getRandomLoot());
                unset($loottable);
            } else if ($val["type"] == "item") {
                print $val["name"] . PHP_EOL;
                //name fix
                if ($val["name"] == "minecraft:fish" || $val["name"] == "fish") $val["name"] = "raw_fish";//TODO proper name fixes via API
                $item = Item::fromString($val["name"]);
                if (isset($val["functions"])) {
                    foreach ($val["functions"] as $function) {
                        switch ($functionname = str_replace("minecraft:", "", $function["function"])) {
                            case "set_damage":
                            {
                                if ($item instanceof Tool) $item->setDamage(mt_rand($function["damage"]["min"] * $item->getMaxDurability(), $function["damage"]["max"] * $item->getMaxDurability()));
                                else $item->setDamage(mt_rand($function["damage"]["min"], $function["damage"]["max"]));
                                break;
                            }
                            case "set_data":
                            {
                                //fish fix, blame mojang
                                switch ($item->getId()) {
                                    case Item::RAW_FISH:
                                    {
                                        switch ($function["data"]) {
                                            case 1:
                                                $item = Item::get(Item::RAW_SALMON, $item->getDamage(), $item->getCount(), $item->getCompoundTag());
                                                break;
                                            case 2:
                                                $item = Item::get(Item::CLOWNFISH, $item->getDamage(), $item->getCount(), $item->getCompoundTag());
                                                break;
                                            case 3:
                                                $item = Item::get(Item::PUFFERFISH, $item->getDamage(), $item->getCount(), $item->getCompoundTag());
                                                break;
                                            default:
                                                break;
                                        }
                                        break;
                                    }
                                    default:
                                    {
                                        $item->setDamage($function["data"]);
                                    }
                                }
                                break;
                            }
                            case "set_count":
                            {
                                $item->setCount(mt_rand($function["count"]["min"], $function["count"]["max"]));
                                break;
                            }
                            case "furnace_smelt":
                            {
                                if (isset($function["conditions"])) {
                                    if (!API::checkConditions($this->entity, $function["conditions"])) break;
                                }
                                // todo foreach condition API::checkConditions
                                if ((!is_null($this->entity) && $this->entity->isOnFire()) || is_null($this->entity))
                                    $item = Server::getInstance()->getCraftingManager()->matchFurnaceRecipe($item)->getResult();
                                break;
                            }
                            case "enchant_randomly":
                            {
                                //TODO
                                break;
                            }
                            case "enchant_with_levels":
                            {
                                /*
                            "function": "enchant_with_levels",
                            "levels": 30,
                            "treasure": true
                                 */
                                //TODO
                                break;
                            }
                            case "looting_enchant":
                            {
                                $item->setCount($item->getCount() + mt_rand($function["count"]["min"], $function["count"]["max"]));
                                break;
                            }
                            case "enchant_random_gear":
                            {
                                break;
                            }
                            case "set_data_from_color_index":
                            {
                                //TODO maybe use ColorBlockMetaHelper::getColorFromMeta();
                                break;
                            }
                            default:
                                assert("Unknown looting table function $functionname, skipping");
                        }
                    }
                }
                $items[] = $item;
            } else if ($val['type'] === "empty") {

            }
        }
        return $items;
    }
}