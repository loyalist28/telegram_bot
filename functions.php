<?
// Функция вызова методов API бота.
function sendTelegram($method, $parameters, $way = 1)
{
	if($way == 1) {
		$ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/' . $method);
		
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		
		$res = curl_exec($ch);
		
		curl_close($ch);
	}
	// Второй способ отправки POST запроса с параметрами. Пока не работает с получением изображений от бота.
	if($way == 2) {
	    $streamContext = stream_context_create(array(
	        'http' => array(
	            'method' => 'POST',
                'headers' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($parameters)
            )
        ));
		file_get_contents('https://api.telegram.org/bot' . TOKEN . '/' . $method, false, $streamContext);
	}	
	
	return $res;
}

/* Функция получения сообщений ответа пользователю от бота. Сообщения хранятся в
файле botMessage.txt и разделены тегами. В параметр $type передаётся строка с
типом сообщения. Доступные типы: 'startMessage', 'setImageName', 'invalidNameError' */
function getBotMessage($type)
{
	function getMessage($tags) 
	{
		$messages = file_get_contents(__DIR__ . '/botMessages.txt');
		
		$startPos = stripos($messages, $tags[0]);
		$endPos = stripos($messages, $tags[1]);
		
		$result = substr($messages, $startPos, $endPos - $startPos);
	
		return $result; 
	}
	
	if($type == 'startMessage') {
		$tags = array('-startMessage', '/-startMessage');
		$messageTextArr = explode('#userName', substr(getMessage($tags), strlen($tags[0] . "\n")));
		
		$messageText = $messageTextArr[0] . $GLOBALS['data']['message']['from']['first_name'] . $messageTextArr[1]; 
		
		return $messageText;
	} elseif($type == 'setImageName') {
	    $tags = array('-setImageName', '/-setImageName');
	    $messageText = substr(getMessage($tags), strlen($tags[0]. "\n"));
	    
	    return $messageText;
	} elseif($type == 'invalidNameError') {
	    $tags = array('-invalidNameError', '/-invalidNameError');
	    $messageText = substr(getMessage($tags), strlen($tags[0] . "\n"));
	    
	    return $messageText;
	}
}

// Функция создания клавиатуры ответа
function createKeyboard($keyboard, $resizeKeyboard = true, $oneTimeKeyboard = false, $selective = false)
{
    $keyboardMarkup = array(
        'keyboard' => $keyboard,
        'resize_keyboard' => $resizeKeyboard,
        'one_time_keyboard' => $oneTimeKeyboard,
        'selective' => $selective
    );
    $keyboardMarkup = json_encode($keyboardMarkup);
    
    return $keyboardMarkup;
}

// Функция отправки списка изображений, хранящихся на сервере.
function getUserImagesList($text = '')
{
    $responseText = $text . "\n\n";
    $imagesDirContent = scandir($GLOBALS['userImagesDir']);
    $imageNum = 1;

    if($GLOBALS['numbersOfUserImgs'] == 0) {
        $responseText = "Сейчас на сервере нет, сохранённых тобой, изображений.\nПришли какое нибудь на хранение или выбери действие.";
    } else {
        foreach ($imagesDirContent as $filename) {
            if (!($filename == '.' || $filename == '..')) {
                $filename = strstr($filename, '.', true);
                $responseText .= $imageNum++ . '. ' . $filename . "\n";
            }
        }
    }

    return $responseText;
}

function getNumOfMsgsInfoStr() 
{
    if($GLOBALS['numbersOfUserImgs'] == 10) {
        return "На сервере хранится 10 твоих изображений. Чтобы добавить новые, нужно удалить какие то старые.\nВыбери действие.";
    } else
        return "Сейчас у меня на хранении {$GLOBALS['numbersOfUserImgs']} твоих изображений.\nПришли изображение или выбери действие.";
}

// Функция для отладки программы.
function debugMessage($message = 'ok')
{
    sendTelegram('sendMessage', array(
        'chat_id' => $GLOBALS['chatId'],
        'text' => print_r($message, true) 
    ));
}
?>