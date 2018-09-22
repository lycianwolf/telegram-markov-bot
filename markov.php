<?php
require("markov-php/Markov.class.php");
$writeHumanReadable = true;

$input = file_get_contents("php://input"); // Retrieve information sent by webhook
$sJ = json_decode($input); // decode JSON supplied by webhook to PHP array
if(!is_object($sJ) || !isset($sJ->message->chat->id)) die("err");

$filterRegEx = [
	"urlFilter" 			=> "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@",
	"punctiationFilter"		=> "/(?<!\w)[.,!]/",
	"newlineFilter"			=> "/\r|\n/"
];

$chatID = $sJ->message->chat->id; // copy for easier access
$rawText = $sJ->message->text;

if(file_exists($chatID . ".mar")) {
	$chain = file_get_contents($chatID . ".mar"); // read serialized object with existing chain
	$markov = unserialize($chain);
} else {
	$markov = new Markov; // create a new chain
}

if(preg_match("/\/(start|status|about|pause|unpause|markov)(\b|$)/", $rawText, $out) && isset($out[1])) {
	switch($out[1]) {
		case "start":
		case "about":
			$text = "I'm a Markov chain bot based on https://github.com/itskenny0/telegram-markov-bot. But edited by Lycian https://github.com/lycianwolf/telegram-markov-bot Send me any text to train my markov chain with it.";
			break;
        case "pause":
            $text = "I'm now don't saving things that you say to me.";
            file_put_contents($chatID . ".paused", "meep");
            break;
        case "unpause":
            $text = "I will now saving things you say again. :)";
            unlink($chatID . ".paused");
            break;
        case "status":
            if(file_exists($chatID . ".paused")){
                $text = "I'm currently not saving Things you say to me.";
            } else {
                $text = "I'm currently saving Things you say to me.";
            }
            break;
		case "markov":
			$text = $markov->generateText(100);
			if(!$text) $text = "Markov chain is empty. Please send some messages first. If the bot is in privacy mode, please remember to tag or PM it.";
			break;
	}
	
	$reply['method'] = "sendMessage";
	$reply['chat_id'] = $chatID;
	$reply['text'] = $text;
	
	header("Content-Type: application/json");
	echo json_encode($reply);
	die();
} else if(!(file_exists($chatID . ".paused"))) {
	$preparedText = strtolower($rawText); // copy the input and convert it to lowercase
	foreach($filterRegEx as $pattern) $preparedText = preg_replace($pattern, " ", $preparedText); // apply the filter regexes above
	$markov->train($preparedText); // add the text to the chain
	
	$chain = serialize($markov); // serialize the markov object to a string
	file_put_contents($chatID . ".mar", $chain); // write it to disk
	if($writeHumanReadable) file_put_contents($chatID . ".mar.txt", print_r($markov, true)); // if human writable is specified, also write a print_r output
	die();
} else {
    die();

}
