<?php
	require("config.php");

	require('vendor/autoload.php');
	use Nesk\Puphpeteer\Puppeteer;
	use Nesk\Rialto\Data\JsFunction;

	include("anticaptcha-php/anticaptcha.php");
	include("anticaptcha-php/nocaptchaproxyless.php");
	$logo=array(
		base64_decode("X19fX19fICAgICAgICAgICAgICAgICAgICAgIF8gICAgICAgICAgICAgICAgX19fX18gICAgICAgICAgICAgIF8gICAgICAgICAgICAKfCBfX18gXCAgICAgICAgICAgICAgICAgICAgKF8pICAgICAgICAgICAgICAvICBfX198ICAgICAgICAgICAgKF8pICAgICAgICAgICAKfCB8Xy8gLyBfICAgXyAgXyBfXyAgXyBfXyAgIF8gIF8gX18gICAgX18gXyBcIGAtLS4gICBfX18gIF8gX18gIF8gICBfX18gIF9fXyAKfCBfX18gXHwgfCB8IHx8ICdfX3x8ICdfIFwgfCB8fCAnXyBcICAvIF9gIHwgYC0tLiBcIC8gXyBcfCAnX198fCB8IC8gXyBcLyBfX3wKfCB8Xy8gL3wgfF98IHx8IHwgICB8IHwgfCB8fCB8fCB8IHwgfHwgKF98IHwvXF9fLyAvfCAgX18vfCB8ICAgfCB8fCAgX18vXF9fIFwKXF9fX18vICBcX18sX3x8X3wgICB8X3wgfF98fF98fF98IHxffCBcX18sIHxcX19fXy8gIFxfX198fF98ICAgfF98IFxfX198fF9fXy8KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgX18vIHwgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB8X19fLyAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA="),
		'LinkFetcher - https://github.com/EvilCorpTM/BurningSeriesLinkFetcher'
	);
	echo implode("\n",$logo);

	if (sizeof($argv) != 2) die("\n\nPlease Use Following Syntax:\nphp -f main.php \"https://bs.to/serie/Drake-and-Josh/de\"\n\n\n");
	$url = $argv[1];
	@mkdir("dl");

	$api = new NoCaptchaProxyless();
	$api->setVerboseMode(false);
	$api->setKey($ANTI_CAPTCHA_KEY);
	$api->setWebsiteKey($BS_CAPTCHA_KEY);
	$api->setWebsiteURL("https://bs.to/");
	echo "Anti-Captcha Balance: USD ".($api->getBalance())."\n\n";

	$puppeteer = new Puppeteer([
		'log_browser_console' => true,
		'idle_timeout' => 480
	]);

	$browser = $puppeteer->launch(['args' => ['--no-sandbox', '--disable-setuid-sandbox']]);
	$page = $browser->newPage();

	function bsRequest($url, $captcha) {
		global $page;
		//echo "bsRequest $url\n";
		$page->goto($url);
		$code=array(
			"var lid = $(\"section.serie .hoster-player\").data(\"lid\"), token=$('meta[name=\"security_token\"]').attr(\"content\");",
			"$.ajax({",
			"	url: 'ajax/embed.php',",
			"	type: 'POST',",
			"	dataType: 'JSON',",
			"	data: {	LID: lid, token: token, ticket: \"$captcha\", },",
			"	success: (e) => document.getElementsByTagName(\"title\")[0].innerText = JSON.stringify(e),",
			"	error: (e) => document.getElementsByTagName(\"title\")[0].innerText = JSON.stringify(e),",
			"})",
			"return 1;"
		);
		$result=$page->evaluate(JsFunction::createWithBody(implode("\n", $code)));
		while (1 <3) {
			$result=$page->evaluate(JsFunction::createWithBody("return document.getElementsByTagName('title')[0].innerText"));
			//echo "$result\n";
			if (strpos($result, "{") === false) { } else {
				break;
			}
			echo ".";
			sleep(1);
		}
		$result=json_decode($result);
		return $result;
	}

	$re1 = '/<a\s+([^>]+\s+)?href\s*=\s*(\'([^\']*)\'|"([^"]*)|([^\s>]+))[^>]*>(.*)<\/a>/m';;
	$data = file_get_contents($url);

	$seasonIndex = substr($data, strpos($data, '<div class="frame" id="seasons">'));
	$seasonIndex = substr($data, strpos($data, '<ul class="clearfix">'));
	$seasonIndex = substr($seasonIndex, 0, strpos($seasonIndex, '</ul>'));
	preg_match_all($re1, $seasonIndex, $matches, PREG_SET_ORDER, 0);

	$title=explode('/',$matches[0][4])[1];
	echo "$title\n";
	@mkdir("dl/$title");

	$indexFile = "dl/$title/index.json";
	$index = array();
	if (!file_exists($indexFile)) file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT)); else $index=json_decode(file_get_contents($indexFile), true);
	//print_r($index);

	foreach ($matches as $season) {
		$url="https://bs.to/".$season[4];
		echo $url."\t".$season[6]."\n";
		$seasonURL=$season[4];

		$episodeData = file_get_contents($url);
		$episodeIndex = substr($episodeData, strpos($episodeData, '<table class="episodes">'));
		$episodeIndex = substr($episodeIndex, 0, strpos($episodeIndex, '</table>'));
		preg_match_all($re1, $episodeIndex, $matches2, PREG_SET_ORDER, 0);

		@mkdir("dl/$title/".$season[6]);
		$linkListPath = "dl/$title/".$season[6]."/links.txt";

		$lastEpisodeIdentifier="";

		foreach ($matches2 as $episode) {
			$a=explode('/', $seasonURL); $country=array_pop($a); $a=implode('/', $a);
			$episodeURL=$episode[4];
			if (strpos($episodeURL, $a) === false) continue;
			//get serie/<SeriesName>/<Season>/<EpisodeName>/<Country>/<StreamSite> into parts
			$b=explode('/', $episodeURL);
			//retrieve the last part <country> or prob. <StreamSite>, if its <Country>, its not a Streamsite, skip it
			if (array_pop($b) == $country) continue;
			$episodeIdentifier=implode('/',$b); // now we have "serie/<SeriesName>/<Season>/<EpisodeName>/<Country>/" without Streamsite, because we already skip the non-Stremasites
			echo "$episodeIdentifier\t$episodeURL\t";
			$episodeSUrl="https://bs.to/".$episodeURL;

			if ($FETCH_ALL_LINKS == false && $episodeIdentifier == $lastEpisodeIdentifier) {
				echo "skipping[p]\n"; continue;
			}
			$api->setWebsiteURL($episodeSUrl);
			if (array_key_exists($episodeIdentifier, $index)) {
				echo "skipping[i]\n"; continue;
			}

			if (!$api->createTask()) {
			    $api->debout("API v2 send failed - ".$api->getErrorMessage(), "red");
			    return false;
			}

			echo "solving captcha...";
			$taskId = $api->getTaskId();
			if (!$api->waitForResult()) {
			        $api->debout("could not solve captcha", "red");
			        $api->debout($api->getErrorMessage());
			        die($api->getErrorMessage());
			} else {
			        $recaptchaToken = $api->getTaskSolution();
			}
			echo "fetching link...";
			$embed = bsRequest($episodeSUrl, $api->getTaskSolution());
			//print_r($embed);
			if ($embed->success) {
				echo "successfull ".$embed->link;
				file_put_contents($linkListPath, $embed->link." ".$season[6]." ".$episodeIdentifier."\n", FILE_APPEND);
				$index[$episodeIdentifier] = array(
					'link' => $embed->link,
					'season' => $season[6],
					'url' => $episodeSUrl,
					'time' => time()
				);
				file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
				echo "\n";
				$lastEpisodeIdentifier=$episodeIdentifier;
			} else {
				echo "unsuccessfull\n";
			}
		}
/*
*/
	}
?>
