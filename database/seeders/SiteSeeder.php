<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ["name" => "youtube", "url" => "https://youtube.com", "icon_path" => "images/youtube.png"],
            ["name" => "Tweakers", "url" => "https://tweakers.net", "icon_path" => "images/tweakers.png", "background_color" => "#cc0033"],
            ["name" => "MyAnimeList", "url" => "https://myanimelist.net", "icon_path" => "images/myAnimeList.png", "background_color" => "#2e51a2"],
            ["name" => "1337x", "url" => "https://1337x.to", "icon_path" => "images/1337x.png"],
            ["name" => "Arknights toolbox", "url" => "https://aceship.github.io/AN-EN-Tags/index.html", "icon_path" => "images/rhodesIsland.png", "background_color" => "black"],
            ["name" => "Github", "url" => "https://github.com/", "icon_path" => "images/github.png"],
            ["name" => "Reddit", "url" => "https://www.reddit.com/", "icon_path" => "images/reddit-min.png"],
            ["name" => "GitLab", "url" => "https://gitlab.com/", "icon_path" => "images/gitlab.png"],
            ["name" => "Twitch", "url" => "https://www.twitch.tv/", "icon_path" => "images/twitch.png", "background_color" => "#593e83"],
            ["name" => "WhatsApp", "url" => "https://web.whatsapp.com/", "icon_path" => "images/whatsapp-min.png"],
            ["name" => "Gmail", "url" => "https://mail.google.com/", "icon_path" => "images/gmail-min.png"],
            ["name" => "GoogleDrive", "url" => "https://drive.google.com/drive/my-drive", "icon_path" => "images/drive.png"],
            ["name" => "GoogleKeep", "url" => "https://keep.google.com/", "icon_path" => "images/googleKeep.png"],
            ["name" => "Chess", "url" => "https://chess.com", "icon_path" => "images/chess.png", "background_color" => "#ffffff"],
            ["name" => "Tabler icons", "url" => "https://tablericons.com/", "icon_path" => "images/tablericons.png"],
            ["name" => "Anilist", "url" => "https://anilist.co/home", "icon_path" => "images/anilist-icon.svg", "background_color" => "#2b2d42"],
            ["name" => "Genshin GG", "url" => "https://genshin.gg/", "icon_path" => "images/GenshinGG.png", "background_color" => "#222431"],
            ["name" => "Nyaa", "url" => "https://nyaa.si/", "icon_path" => "images/nyaa.png", "background_color" => "#0084ff"],
            ["name" => "use hooks", "url" => "https://usehooks.com/", "icon_path" => "images/tablericons.png", "background_color" => "#fff"],
            [
                "name" => "Subs please", "url" => "https://subsplease.org/", "icon_path" => "images/iseria.png", "background_color" => "#fbf5f0"], [
                "name" => "Pulse", "url" => "https://pulse.scrumble.nl/", "icon_path" => "images/pulse-min.png", "background_color" => "#3f4852"], [
                "name" => "Font awesome", "url" => "https://fontawesome.com", "icon_path" => "images/font-awesome.png", "background_color" => "#528cd7"], [
                "name" => "Jenkins", "url" => "https://jenkins.scrumble.customer.cloud.nl", "icon_path" => "images/jenkins.png", "background_color" => "black"], [
                "name" => "RegExr", "url" => "https://regexr.com", "icon_path" => "images/regexr-logo.png", "background_color" => "#70b1e1"], [
                "name" => "Duolingo", "url" => "https://www.duolingo.com/learn", "icon_path" => "images/duolingo.svg", "background_color" => "#50c800"], [
                "name" => "Aniwave", "url" => "https://aniwave.to/home", "icon_path" => "images/aniwave.png", "background_color" => "#1c1c1c"], [
                "name" => "ChatGPT", "url" => "https://chat.openai.com", "icon_path" => "images/openai.svg", "background_color" => "#74AA9C"], [
                "name" => "Home Assistant", "url" => "https://home-assistant.spyx.family", "icon_path" => "images/home-assistant.svg", "background_color" => "#41BDF5"]
        ];

        foreach ($data as $site) {
            Site::create($site);
        }

    }
}
