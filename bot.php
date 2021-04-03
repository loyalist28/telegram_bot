<?
//Подключение функций для работы бота.
include './functions.php';

//Определяем константу с токеном бота.
define('TOKEN', '1734558749:AAE7gkzVxtXtAU4Pyl1XA8bQU-sqF5pKCMo');

//Подключение к БД для работы с данными пользователей
$dataBaseAuthInfo = explode(' ', file_get_contents('./dataBaseAuthInfo.txt'));
$dbLink = mysqli_connect('localhost', $dataBaseAuthInfo[0], $dataBaseAuthInfo[1], 'usersdata');

//Получаем данные обновления от бота.
$data = file_get_contents('php://input');
$data = json_decode($data, true);
$text = $data['message']['text'];
$chatId = $data['message']['chat']['id'];
$userId = $data['message']['from']['id'];
$userDirectory = './usersDirectories/User' . $userId;
$userImagesDir = $userDirectory . '/images';
$removeKeyboard = json_encode(array('remove_keyboard' => true)); // Убираем клавиатуру когда не нужна.
$cancelKeyboard = array('inline_keyboard' => [[['text' => 'Отмена', 'callback_data' => $chatId]]]);
$cancelKeyboard = json_encode($cancelKeyboard); // Инлайн клавиша отмены ввода
// Получаем колличество сохранённых изображений пользователя
$userDataFromDB = mysqli_query($dbLink, "SELECT*FROM usersdata WHERE user_id={$userId}");
$numbersOfUserImgs = mysqli_fetch_array($userDataFromDB, MYSQLI_NUM)[2];

//Файл для хранения состояния взаимодействия с ботом между запусками скрипта.
$flagFile = $userDirectory . '/flagFile.txt';

//Основная клавиатура для взаимодействия с ботом.
$keyboard = array(
    ['Вывести список изображений'],
    ['Получить изображение'],
    ['Удалить изображение']
);
$mainActionsKeyboard = createKeyboard($keyboard, true);

//Ответы бота
if($text) {
    if(file_get_contents($flagFile)) {
        //Отправка изображения пользователю
        if(file_get_contents($flagFile) == 'receive') {
            $imagesDirContent = scandir($userImagesDir);

            foreach($imagesDirContent as $filename) {
                if(preg_match("/^{$text}(\.[a-z]{3,4})/u", $filename, $out)) {
                    $imageFile = curl_file_create($userImagesDir . '/' . $out[0]);
                }
            }
            if($imageFile) {
                $keyboard = [['Отлично. Спасибо, бот!']];
                $replyMarkup = createKeyboard($keyboard, true, true);

                sendTelegram('sendPhoto', array(
                    'chat_id' => $chatId,
                    'photo' => $imageFile,
                    'caption' => "Держи, \"$text\". Как и просил ;)",
                    'reply_markup' => $replyMarkup
                ));

                file_put_contents($flagFile, '');
                exit();
            } else {
                $responseText = "Изображения \"$text\" нет у меня на сервере :\ \n" .
                "Возможно допущенна ошибка. Попробуй ещё раз.";

                sendTelegram('sendMessage', array(
                    'chat_id' => $chatId,
                    'text' => getUserImagesList($responseText),
                    'reply_markup' => $cancelKeyboard
                ));

                exit();
            }
        } elseif(file_get_contents($flagFile) == 'delete') {
            $imagesDirContent = scandir($userImagesDir);

            foreach($imagesDirContent as $filename) {
                if(preg_match("/^$text\.[a-z]{3,4}/", $filename, $out)) {
                    unlink($userImagesDir . '/' . $out[0]);

                    $numbersOfUserImgs -= 1;
                    mysqli_query($dbLink, "UPDATE usersdata SET numbersOfImgs={$numbersOfUserImgs} WHERE user_id={$userId}");

                    $responseText = "Изображение \"$text\" удалено с сервера.\n\n" . getNumOfMsgsInfoStr();

                    sendTelegram('sendMessage', array(
                        'chat_id' => $chatId,
                        'text' => $responseText,
                        'reply_markup' => $mainActionsKeyboard
                    ));

                    file_put_contents($flagFile, '');
                    exit();
                }
            }

            $responseText = "Изображения \"$text\" нет у меня на сервере :\ \n" .
            "Возможно допущенна ошибка. Попробуй ещё раз.";

            sendTelegram('sendMessage', array(
                'chat_id' => $chatId,
                'text' => getUserImagesList($responseText),
                'reply_markup' => $cancelKeyboard
            ));

            exit();
        } else {
            if(preg_match('/^[\wа-яА-Я\s]+$/u', $text)) {
                $src = file_get_contents($flagFile);
                $dest = $userImagesDir. '/' . $text . strstr(basename($src), '.');

                copy($src, $dest);

                $numbersOfUserImgs += 1;
                mysqli_query($dbLink, "UPDATE usersdata SET numbersOfImgs={$numbersOfUserImgs} WHERE user_id={$userId}");

                $responseText = "Сохранил! Теперь это изображение доступно под именем \"$text\".\n\n" .
                getNumOfMsgsInfoStr();

                sendTelegram('sendMessage', array(
                    'chat_id' => $chatId,
                    'text' => $responseText,
                    'reply_markup' => $mainActionsKeyboard
                ));


                file_put_contents($flagFile, '');
                exit();
            }

            sendTelegram('sendMessage', array(
                'chat_id' => $chatId,
                'text' => getBotMessage('invalidNameError')
            ));

            exit();
        }
    } elseif($text == '/start') {
        // Если пользователя ещё нет в БД, то добавить его туда.
        if(!(mysqli_query($dbLink, "SELECT*FROM usersdata WHERE user_id={$userId}")->num_rows == 1)) {
            mysqli_query($dbLink, "INSERT usersdata(user_id, numbersOfImgs) VALUES ({$userId}, 0)");
        }

        mkdir($userImagesDir, 0755, true);
        file_put_contents($flagFile, '');

        $replyMarkup = createKeyboard([['Понятно. Начнём!']], true, true);

        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => getBotMessage('startMessage'),
            'reply_markup' => $replyMarkup
        ));

        exit();
    } elseif($text == 'Понятно. Начнём!' || $text == 'Понятно')
    {
        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => getNumOfMsgsInfoStr(),
            'reply_markup' => $mainActionsKeyboard
        ));

        exit();
    } elseif ($text == 'Вывести список изображений')
    {
        $responseText = "Вот список твоих изображений, хранящихся у меня на сервере:";
        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => getUserImagesList($responseText),
            'reply_markup' => $mainActionsKeyboard
        ));

        exit();
    } elseif($text == 'Получить изображение') {
        if($numbersOfUserImgs == 0)
        {
            sendTelegram('sendMessage', array(
                'chat_id' => $chatId,
                'text' => "Сейчас на сервере нет, сохранённых тобой, изображений.\nПришли какое нибудь на хранение или выбери действие.",
                'reply_markup' => $mainActionsKeyboard
            ));

            exit();
        }

        file_put_contents($flagFile, 'receive');

        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => "Введи название изображения, которое хочешь получить.",
            'reply_markup' => $removeKeyboard
        ));

        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => getUserImagesList(),
            'reply_markup' => $cancelKeyboard
        ));

        exit();
    } elseif($text == 'Удалить изображение') {
        if($numbersOfUserImgs == 0)
        {
            sendTelegram('sendMessage', array(
                'chat_id' => $chatId,
                'text' => "Сейчас на сервере нет, сохранённых тобой, изображений.\nПришли какое нибудь на хранение или выбери действие.",
                'reply_markup' => $mainActionsKeyboard
            ));

            exit();
        }

        file_put_contents($flagFile, 'delete');

        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => "Введи название изображения, которое хочешь получить.",
            'reply_markup' => $removeKeyboard
        ));

        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => getUserImagesList(),
            'reply_markup' => $cancelKeyboard
        ));

        exit();
    } elseif($text == 'Отлично. Спасибо, бот!')
    {
        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => getNumOfMsgsInfoStr(),
            'reply_markup' => $mainActionsKeyboard
        ));
        
        exit();
    }
    // Если пользователь набрал "случайное" сообщение
    sendTelegram('sendMessage', array(
        'chat_id' => $chatId,
        'text' => "Не совсем понял тебя. :\ \nПопробуй ещё раз.",
        'reply_markup' => $mainActionsKeyboard
        ));
        
    exit();
} elseif($data['message']['photo']) {
    /* Если прислали изображение получаем ссылку на него и просим пользователя
    назвать, переданное им, изображение. */
    if($numbersOfUserImgs == 10) {
        sendTelegram('sendMessage', array(
                'chat_id' => $chatId,
                'text' => "Хранилище заполнено!\n\nНа сервере хранится 10 твоих изображений. Чтобы добавить новые, нужно удалить какие то старые.\nВыбери действие.",
                'reply_markup' => $mainActionsKeyboard
            ));

        exit();
    }

	$image = array_pop($data['message']['photo']);
	$imageData = sendTelegram('getFile', array('file_id' => $image['file_id']));
	$imageData = json_decode($imageData, true);
	
	if($imageData['ok']) {
		$src = 'https://api.telegram.org/file/bot' . TOKEN . '/' . $imageData['result']['file_path'];
		file_put_contents($flagFile, $src);

        sendTelegram('sendMessage', array(
            'chat_id' => $chatId,
            'text' => "Введи название для этого изображения.\n\n",
            'reply_markup' => $removeKeyboard
        ));

		sendTelegram('sendMessage', array(
		    'chat_id' => $chatId,
		    'text' => getBotMessage('setImageName'),
		    'reply_markup' => $cancelKeyboard
		));
		
		exit();
	}
} elseif($data['callback_query'])
{
    $chatId = $data['callback_query']['data'];
    $userId = $data['callback_query']['from']['id'];
    $userDirectory = './usersDirectories/User' . $userId;
    $flagFile = $userDirectory . '/flagFile.txt';
    $userDataFromDB = mysqli_query($dbLink, "SELECT*FROM usersdata WHERE user_id={$userId}");
    $numbersOfUserImgs = mysqli_fetch_array($userDataFromDB, MYSQLI_NUM)[2];

    sendTelegram('answerCallbackQuery', array('callback_query_id' => $data['callback_query']['id']));

    sendTelegram('sendMessage', array(
        'chat_id' => $chatId,
        'text' => "Ввод отменён.\n\n" . getNumOfMsgsInfoStr(),
        'reply_markup' => $mainActionsKeyboard
    ));

    file_put_contents($flagFile, '');
    exit();
}

$keyboard = [['Понятно']];
$replyMarkup = createKeyboard($keyboard, true, true);
$responseText = "Данный функционал пока не поддерживается.\n\n";
    
sendTelegram('sendMessage', array(
    'chat_id' => $chatId,
    'text' => $responseText,
    'reply_markup' => $replyMarkup
));
?>