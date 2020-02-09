<?php

namespace xenialdan\mobloot;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginException;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\Server;
use RuntimeException;

class API
{
    public static $loottables = [];

    /**
     * Must be called to initialize the virion
     * Searches for loot_table files in behaviour packs and caches their json data
     * @throws RuntimeException
     */
    public static function init(): void
    {
        if (!empty(self::$loottables)) return; //avoid double registering
        foreach (Server::getInstance()->getResourcePackManager()->getResourceStack() as $resourcePack) {//TODO check if the priority is ordered in that way, that the top pack overwrites the lower packs
            if ($resourcePack instanceof ZippedResourcePack) {
                $za = new \ZipArchive();

                $za->open($resourcePack->getPath());

                for ($i = 0; $i < $za->numFiles; $i++) {
                    $stat = $za->statIndex($i);
                    if (explode(DIRECTORY_SEPARATOR, $stat['name'])[0] === "loot_tables") {
                        self::$loottables[str_replace(DIRECTORY_SEPARATOR, "/", str_replace(".json", "", $stat['name']))] = json_decode($za->getFromIndex($i), true);
                    }
                }

                $za->close();
            }
        }
    }

    public static function checkConditions(Entity $entity, array $conditions)
    {
        $target = null;
        foreach ($conditions as $value) {
            switch ($value["condition"]) {
                case "entity_properties":
                {//function condition
                    switch ($value["entity"]) {
                        case "this":
                        {
                            $target = $entity;
                            break;
                        }
                        default:
                        {
                            print("(Yet) Unknown target type: " . $value["entity"] . PHP_EOL);
                            return false;
                        }
                    }
                    foreach ($value["properties"] as $property => $propertyValue) {
                        switch ($property) {
                            case "on_fire":
                            {
                                if (!$target->isOnFire()) return false;
                                break;
                            }
                            default:
                            {
                                print("(Yet) Unknown entity property: " . $property . PHP_EOL);
                                return false;
                            }
                        }
                    }
                    break;
                }
                case "killed_by_player":
                {//roll condition
                    // TODO recode/recheck/recode etc
                    if (($event = $entity->getLastDamageCause()) instanceof EntityDamageEvent and $event instanceof EntityDamageByEntityEvent) {//TODO fix getLastDamageCause on null
                        if (!$event->getDamager() instanceof Player) return false;
                    }
                    break;
                }
                case "killed_by_entity":
                {//roll condition
                    // TODO recode/recheck/recode etc
                    if (($event = $entity->getLastDamageCause()) instanceof EntityDamageEvent and $event instanceof EntityDamageByEntityEvent) {//TODO fix getLastDamageCause on null
                        $damager = $event->getDamager();
                        if ($event instanceof EntityDamageByChildEntityEvent) {
                            $damager = $event->getChild()->getOwningEntity();
                        }
                        print("========= SAVE ID OF DAMAGER =========" . PHP_EOL);
                        print($damager->getSaveId() . PHP_EOL);
                        print("========= SEARCHED FOR =========" . PHP_EOL);
                        print($value["entity_type"] . PHP_EOL);
                        if ($event->getDamager()->getSaveId() !== $value["entity_type"]) return false;
                    }

                    break;
                }
                case "random_chance_with_looting":
                {//roll condition
                    print("========= CHANCE =========" . PHP_EOL);
                    print($value["chance"] . PHP_EOL);
                    print("========= LOOTING_MULTIPLIER =========" . PHP_EOL);
                    print($value["looting_multiplier"] . PHP_EOL);
                    break;
                }
                case "random_difficulty_chance":
                {//loot condition //return nothing yet, those are roll-repeats or so
                    print("========= CHANCE =========" . PHP_EOL);
                    print($value["default_chance"] . PHP_EOL);
                    print("========= CHANCE FITTING THE DIFFICULTY =========" . PHP_EOL);
                    //TODO
                    foreach ($value as $difficultyString => $chance) {
                        print($difficultyString . " => " . $chance . PHP_EOL);
                        if ($entity->getLevel()->getDifficulty() === Level::getDifficultyFromString($difficultyString)) {
                            print("========= CHANCE =========" . PHP_EOL);
                            print($chance . PHP_EOL);
                        }
                    }
                    break;
                }
                case "random_regional_difficulty_chance":
                {//roll condition
                    //TODO
                    //no break, send default message
                }
                default:
                {
                    print("(Yet) Unknown condition: " . $value["condition"] . PHP_EOL);
                }
            }

        }
        return true;
    }

    /**
     * Returns the subject that the test should run on
     * @param Entity $caller
     * @param null|Entity $other
     * @param string $target
     * @return null|Entity
     */
    public static function targetToTest(Entity $caller, ?Entity $other, string $target): ?Entity
    {
        switch ($target) {
            //The other member of an interaction, not the caller
            case "other":
            {
                return $other;
                break;
            }
            //TODO The caller's current parent
            case "parent":
            {
                return null;//Not possible yet!
                break;
            }
            //TODO The player involved with the interaction --Could possibly be even another entity?
            case "player":
            {
                return ($other instanceof Player) ? $other : null;
                break;
            }
            //The entity or object calling the test
            case "self":
            {
                return $caller;
                break;
            }
            //The caller's current target
            case "target":
            {
                return $caller->getTargetEntity();
                break;
            }
        }
        return null;
    }

    public static function getMinAABB(AxisAlignedBB $aabb): Vector3
    {
        return new Vector3($aabb->minX, $aabb->minY, $aabb->minZ);
    }

    public static function getMaxAABB(AxisAlignedBB $aabb): Vector3
    {
        return new Vector3($aabb->maxX, $aabb->maxY, $aabb->maxZ);
    }

    public static function getAABBCorners(AxisAlignedBB $aabb): array
    {
        return [
            new Vector3($aabb->minX, $aabb->minY, $aabb->minZ),
            new Vector3($aabb->minX, $aabb->minY, $aabb->maxZ),
            new Vector3($aabb->minX, $aabb->maxY, $aabb->minZ),
            new Vector3($aabb->minX, $aabb->maxY, $aabb->maxZ),
            new Vector3($aabb->maxX, $aabb->minY, $aabb->minZ),
            new Vector3($aabb->maxX, $aabb->minY, $aabb->maxZ),
            new Vector3($aabb->maxX, $aabb->maxY, $aabb->minZ),
            new Vector3($aabb->maxX, $aabb->maxY, $aabb->maxZ),
        ];
    }

    /**
     * https://stackoverflow.com/a/11872928/4532380
     * getRandomWeightedElement()
     * Utility function for getting random values with weighting.
     * Pass in an associative array, such as array('A'=>5, 'B'=>45, 'C'=>50)
     * An array like this means that "A" has a 5% chance of being selected, "B" 45%, and "C" 50%.
     * The return value is the array key, A, B, or C in this case.  Note that the values assigned
     * do not have to be percentages.  The values are simply relative to each other.  If one value
     * weight was 2, and the other weight of 1, the value with the weight of 2 has about a 66%
     * chance of being selected.  Also note that weights should be integers.
     *
     * @param int[] $weightedValues
     * @return mixed $key, -1 if failed
     * @throws PluginException
     */
    public static function getRandomWeightedElement(array $weightedValues)
    {
        if (empty($weightedValues)) {
            throw new PluginException("The weighted values are empty");
        }
        $rand = mt_rand(1, (int)array_sum($weightedValues));

        foreach ($weightedValues as $key => $value) {
            $rand -= $value;
            if ($rand <= 0) {
                return $key;
            }
        }
        return -1;
    }

    public static function clamp($current, $min, $max)
    {
        return max($min, min($max, $current));
    }
}