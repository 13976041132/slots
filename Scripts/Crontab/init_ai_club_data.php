<?php
namespace FF\Scripts\Crontab;

use FF\Bll\ClubBll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

include __DIR__ . '/../common.php';

$club = Model::clubs()->fetchOne(['dan' => ['<=', 10], 'ai' => 1], 'clubId');
if ($club) {
    return;
}

$clubNames = 'Stormbreaker|Celestial|Phoenix|Moonlight|Thunder|Starry|Crystal|Shadow|Blaze|Aurora|Iron|Eternal|Serpent|Frostborn|Astral|Titan|Reborn|Soulforge|Gaia|Vanguard|allure love|whisper|entai|kite|woundaged|Surehurt|cloudy|Life|Love|my lover|depths|Invader|Daer|mss|sweet|Summer|Nightmare|Cowardice|cardiac|entai|Weirdo|Crayon|catch me|triste|finlandia|pain|tourist|struggle|Misay|delusion|paranoid|S haron|unfinished|soleil|Treason|Doershow|moent|lantau|LAY|feeling|BIGWIN|MEGAWIN|SUPERWIN|JACKPOT|888|struggle|Triste|Aimee|Ending|vapaus|silence|BOOM|Endless|Melted|Proven|SEND OUT|slaughterer|MONSTER|cardiac|Replace|tenderness|Cowardice|whisper|SUnBoC|Beak Hyun|Soul mates|timber|Honoria|destructi|Luminary|ice|BUBU|Rainy|golden|PANDA|JONNY|GodOfWealth|bison|casino|SlotsClub|Perish|kalsotra|divining|ScentFlavor|Uprman|Shadow|D.O|Levy|Triste|Nsane|Shouting|Tourist|AvecleSoleil|FORGOTTEN|STRUGGLE|Forever|Bigbang|HeartSlave|RAINY|ZZang|Fundus|Peach|biubiubiu|Happy|Silence|MoonLight|Jerry|NICEWIN|Baby|OldBaby|Weirdo|Dream|DreamSlots|Stubborn|Juice|ThePupl|Gehenna|Blank|Wind|Eason|Lovely|Cloudy|GiveUp|Melted|Demon|PLAY YOU|Run|Tunder|KissOrHugs|STAY|Endless|CasinoCity|BeLost|TEAM|Hippie|HippieTeam|BOSS|Referee|Judge|SCHOOL|Shadow|PHOENIX|Eclipse|Vortex|Aegis|Ravage|Nova|Onyx|Fury|Ironclad|Storm|Viper|Rogue|Havoc|Titan|Blitz|Wraith|Serpent|Legion|Vanguard|Doom|Rift|Inferno|Celestial|Obsidian|Revenant|Valkyrie|Savage|Thunder|Nemesis|Spectre|Abyss|Risen|Warden|Crimson|Frost|Solaris|Vex|Zenith|Rust|Apex|Void|Chrono|Dread|Mythic|Pandora|Quake|Spartan|Titanic|Umbra|Wolfpack|Xenon|Yggdrasil|Zephyr|Rune|Omen|Lunar|Knight|Juggernaut|Hydra|Glory|Frostbite|Ember|Dragoon|Cursed|Blight|Ashen|Arcanum|Berserk|Chaos|Dragon|Eternal|Fallen|Ghost|Havok|Immortal|Judgment|Kraken|Lich|Maelstrom|Nightfall|Oblivion|Paragon|Quantum|Ragnarok|Stalker|Tempest|Undying|Vendetta|Warlock|Xero|Yojimbo|Zealot|Alpha|Beta|Gamma|Delta|Sigma|Omega|Neon|Radiance|Scourge|Templar|Uprising|Vigil|Wildfire|Exile|Ymir|Zodiac|Avalon|Blackout|Catalyst|Dominion|Enigma|Forsaken|Grimoire|Horizon|Illusion|Jade|Karma|Luminous|Mirage|Nebula|Oracle|Prestige|Quicksand|Rebirth|Sanctum|Tranquil|Unbroken|Virtue|Wanderer|Xcalibur|Yonder|Zodiac|Aegis|Bane|Cinder|Divine|Echo|Flux|Gale|Haven|Infinity|Justice|Kings|Loyalty|Majesty|Noble|Oath|Purity|Quest|Royal|Sacred|Triumph|Unity|Valor|Wisdom|Xenith|Yield|Zest|Aurora|Blaze|Crest|Dawn|Elite|Fable|Guardian|Honor|Ignite|Jinx|Knightly|Legend|Mythos|Nimbus|Outlaw|Prowler|Quasar|Rogue|Sable|Talon|Unseen|Vandal|Wicked|Xenon|Yonder|Zephyr|Aether|Bulwark|Cipher|Dusk|Elysium|Frost|Grim|Hollow|Icarus|Jester|Kaiser|Lynx|Mystic';
$clubNames = explode('|', $clubNames);
$clubNames = array_unique($clubNames);
shuffle($clubNames);

//根据赛季俱乐部信息
$commCfg = Config::get('club/common');
$gradeConfig = Config::get('club/grade');
$aiCfg = Config::get('club/ai');
$levelCfg = Config::get('club/level');
$defClubId = 9000;
$aiClubs = [];
foreach ($gradeConfig as $gradeInfo) {
    $num = (int)array_sum($gradeInfo['aiProportion']);
    $cntLevel = array_column($levelCfg, 'clubLevel', 'member');
    foreach ($aiCfg as $aiInfo) {
        if ($aiInfo['grade'] != $gradeInfo['grade']) {
            continue;
        }
        $level = $aiInfo['level'];
        if (!isset($gradeInfo['aiProportion'][$level - 1])) {
            continue;
        }
        $cunt = ceil($commCfg['aiClub'] * $gradeInfo['aiProportion'][$level - 1] / $num);

        for ($i = 0; $i < $cunt; $i++) {
            $clubName = array_pop($clubNames);
            if(!$clubName) {
                break;
            }
            $aiClubs[] = [
                'clubId' => $defClubId++,
                'creator' => $defClubId,
                'dan' => $gradeInfo['id'],
                'type' => ClubBll::TYPE_PRIVATE,
                'ai' => 1,
                'level' => $cntLevel[$aiInfo['member']] ?? 1,
                'memberCnt' => $aiInfo['member'],
                'aiActLevel' => $level,
                'clubName' => $clubName,
            ];
        }
    }
}

if ($aiClubs) {
    Model::clubs()->insertMulti($aiClubs);
}