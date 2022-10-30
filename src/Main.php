<?php

namespace Nyrok\TotemOfFertility;

use GdImage;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\color\Color;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\world\particle\BlockForceFieldParticle;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\EnchantParticle;
use pocketmine\world\particle\EntityFlameParticle;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\world\particle\InkParticle;
use pocketmine\world\particle\InstantEnchantParticle;
use pocketmine\world\particle\ItemBreakParticle;
use pocketmine\world\particle\LavaDripParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\RainSplashParticle;
use pocketmine\world\particle\RedstoneParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\particle\SplashParticle;
use pocketmine\world\particle\SporeParticle;
use pocketmine\world\particle\TerrainParticle;
use pocketmine\world\particle\WaterDripParticle;
use pocketmine\world\particle\WaterParticle;
use pocketmine\world\World;

class Main extends PluginBase
{
    use SingletonTrait;

    public static string $scoretag;
    public static string $nametag;
    public static string $texture;
    public static string $identifier;
    public static string $geometry;
    public static float $growthTime;
    public static int $radius;
    public static int $health;
    public static int $blockId;
    public static int $fuelItemId;
    private Config $config;

    protected function onLoad(): void
    {
        $this::setInstance($this);
    }

    protected function onEnable(): void
    {
        $this->saveResource('config.yml', false);
        $this->saveResource('totem.png', false);
        $this->saveResource('totem.json', false);
        $this->config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        EntityFactory::getInstance()->register(Totem::class, function (World $world, CompoundTag $nbt): Totem {
            return new Totem(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['TotemOfFertility']);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);

        self::$health = $this->getConfig()->get('max-health', 250);
        self::$nametag = $this->getConfig()->get('nametag', '§cTotem of Fertility');
        self::$scoretag = $this->getConfig()->get('scoretag', '§6x{fuel} Paladium');
        self::$growthTime = (float)$this->getConfig()->get('growth-time', 1);
        self::$radius = $this->getConfig()->get('radius', 5);
        self::$identifier = $this->getConfig()->get('identifier', 'geometry.totem');
        self::$geometry = $this->getConfig()->get('geometry', 'totem.json');
        self::$texture = $this->getConfig()->get('texture', 'totem.png');
        self::$blockId = $this->getConfig()->get('block-id', BlockLegacyIds::GOLD_BLOCK);
        self::$fuelItemId = $this->getConfig()->get('fuel-item-id', ItemIds::GOLD_INGOT);
    }

    public function PNGtoBYTES($path): string
    {
        $img = @imagecreatefrompng($path);
        $bytes = "";
        $L = (int)@getimagesize($path)[0];
        $l = (int)@getimagesize($path)[1];
        for ($y = 0; $y < $l; $y++) {
            for ($x = 0; $x < $L; $x++) {
                $rgba = @imagecolorat($img, $x, $y);
                $a = ((~($rgba >> 24)) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        if ($img instanceof GdImage) @imagedestroy($img);
        return $bytes;
    }

    public function getParticle(string $name, ?int $data = null): ?Particle
    {
        switch ($name) {
            case "explode":
                return new ExplodeParticle();
            case "hugeexplosion":
                return new HugeExplodeParticle();
            case "hugeexplosionseed":
                return new HugeExplodeSeedParticle();
            case "bubble":
                return new BubbleParticle();
            case "splash":
                return new SplashParticle();
            case "wake":
            case "water":
                return new WaterParticle();
            case "crit":
                return new CriticalParticle();
            case "smoke":
                return new SmokeParticle($data ?? 0);
            case "spell":
                return new EnchantParticle(new Color(0, 0, 0, 255)); //TODO: colour support
            case "instantspell":
                return new InstantEnchantParticle(new Color(0, 0, 0, 255)); //TODO: colour support
            case "dripwater":
                return new WaterDripParticle();
            case "driplava":
                return new LavaDripParticle();
            case "townaura":
            case "spore":
                return new SporeParticle();
            case "portal":
                return new PortalParticle();
            case "flame":
                return new FlameParticle();
            case "lava":
                return new LavaParticle();
            case "reddust":
                return new RedstoneParticle($data ?? 1);
            case "snowballpoof":
                return new ItemBreakParticle(VanillaItems::SNOWBALL());
            case "slime":
                return new ItemBreakParticle(VanillaItems::SLIMEBALL());
            case "itembreak":
                if ($data !== null && $data !== 0) {
                    return new ItemBreakParticle(ItemFactory::getInstance()->get($data));
                }
                break;
            case "terrain":
                if ($data !== null && $data !== 0) {
                    return new TerrainParticle(BlockFactory::getInstance()->get($data, 0));
                }
                break;
            case "heart":
                return new HeartParticle($data ?? 0);
            case "ink":
                return new InkParticle($data ?? 0);
            case "droplet":
                return new RainSplashParticle();
            case "enchantmenttable":
                return new EnchantmentTableParticle();
            case "happyvillager":
                return new HappyVillagerParticle();
            case "angryvillager":
                return new AngryVillagerParticle();
            case "forcefield":
                return new BlockForceFieldParticle($data ?? 0);
            case "mobflame":
                return new EntityFlameParticle();
        }

        if (str_starts_with($name, "iconcrack_")) {
            $d = explode("_", $name);
            if (count($d) === 3) {
                return new ItemBreakParticle(ItemFactory::getInstance()->get((int)$d[1], (int)$d[2]));
            }
        } elseif (str_starts_with($name, "blockcrack_")) {
            $d = explode("_", $name);
            if (count($d) === 2) {
                return new TerrainParticle(BlockFactory::getInstance()->get(((int)$d[1]) & 0xff, ((int)$d[1]) >> 12));
            }
        } elseif (str_starts_with($name, "blockdust_")) {
            $d = explode("_", $name);
            if (count($d) >= 4) {
                return new DustParticle(new Color(((int)$d[1]) & 0xff, ((int)$d[2]) & 0xff, ((int)$d[3]) & 0xff, isset($d[4]) ? ((int)$d[4]) & 0xff : 255));
            }
        }

        return null;
    }

}