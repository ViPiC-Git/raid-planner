<?php # 0.2.5d api для бота в discord

include_once "../../libs/File-0.1.inc.php";                                 // 0.1.5 класс для многопоточной работы с файлом
include_once "../../libs/FileStorage-0.5.inc.php";                          // 0.5.9 подкласс для работы с файловым реляционным хранилищем
include_once "../../libs/phpEasy-0.3.inc.php";                              // 0.3.7 основная библиотека упрощённого взаимодействия
include_once "../../libs/vendor/webSocketClient-1.0.inc.php";               // 0.1.0 набор функций для работы с websocket

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$app = array(// основной массив данных
    "val" => array(// переменные и константы
        "baseUrl" => "../base/%name%.db",                                   // шаблон url для базы данных
        "statusUnknown" => "Server unknown status",                         // сообщение для неизвестного статуса
        "statusLang" => "en",                                               // язык для кодов расшифровок
        "format" => "json",                                                 // формат вывода поумолчанию
        "eventTimeAdd" => 8*24*60*60,                                       // максимальное время для записи в событие
        "eventTimeDelete" => 12*60*60,                                      // максимальное время хранения записи события
        "eventTimeClose" => -15*60,                                         // время за которое закрывается событие для изменения
        "eventTimeStep" => 30*60,                                           // шаг записи на событие (округление времени)
        "eventTimeLimit" => 1,                                              // лимит записей на время для пользователя
        "eventRaidLimit" => 1,                                              // лимит записей на время и рейд для пользователя
        "eventCommentLength" => 300,                                        // максимальная длина комментария пользователя
        "eventNoteLength" => 20,                                            // максимальная длина заметки пользователя
        "eventOperationCycle" => 5,                                         // с какой частотой производить регламентные операции с базой в цикле
        "discordApiUrl" => "https://discordapp.com/api",                    // базовый url для взаимодействия с Discord API
        "discordWebSocketHost" => "gateway.discord.gg",                     // адрес хоста для взаимодействия с Discord через WebSocket
        "discordWebSocketLimit" => 1500,                                    // лимит итераций цикла общения WebSocket с Discord
        "discordMessageLength" => 2000,                                     // максимальная длина сообщения в Discord
        "discordMessageTime" => 6*60,                                       // максимально допустимое время между сгруппированными сообщениями
        "discordCreatePermission" => 32768,                                 // разрешения для создание первой записи в событие (прикреплять файлы)
        "discordUserPermission" => 16384,                                   // разрешения для записи других пользователей (встраивать ссылки)
        "discordBotPermission" => 76800,                                    // минимальные разрешения для работы бота
        "discordBotGame" => "рейды от @ViPiC#5562",                         // анонс возле аватарки бота
        "appToken" => "MY-APP-TOKEN",                                       // защитный ключ приложения
        "discordBotId" => "MY-DISCORD-BOT-ID",                              // идентификатор приложения в Discord
        "discordBotToken" => "MY-DISCORD-BOT-TOKEN"
    ),
    "base" => array(// базы данных
    ),
    "cache" => array(// кеш данных
    ),
    "method" => array(// поддерживаемые методы
        "discord.connect" => function($params, $options, $sign, &$status){// выполняем подключение
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $isSessionUpdate = false;// были ли обновлены данные в базе данных
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if(!is_null($token) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if(!empty($token) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]("session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // обрабатываем циклически все уведомления
            if(empty($status)){// если нет ошибок
                $index = 0;// индекс итераций цикла
                $websocket = null;// подключение через веб-сокет
                $heartbeatSendTime = 0;// время отправка последнего серцебиения
                $heartbeatAcceptTime = 0;// время ответа на последнее серцебиение
                $heartbeatInterval = 0;// интервал отправка серцебиения
                do{// выполняем циклическую обработку
                    // получаем данные из подключения
                    if($websocket){// если создано подключение
                        $data = websocket_read($websocket, $error);
                        if($data) $data = json_decode($data, true);
                    }else $data = array("op" => 7);// reconnect
                    $now = microtime(true);// текущее время
                    // обрабатываем код уведомления
                    switch(get_val($data, "op", null)){// поддерживаемые коды
                        case 0:// dispatch
                            // обрабатываем тип уведомления
                            switch(get_val($data, "t", null)){// поддерживаемые типы
                                case "READY":// ready
                                    // обрабатываем начало подключения
                                    if(isset($data["d"]["session_id"])){// если есть обязательное значение
                                        if($session->set("sid", "value", $data["d"]["session_id"])){// если данные успешно добавлены
                                            $isSessionUpdate = true;// были обновлены данные в базе данных
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                case "RESUMED":// resumed
                                    // обрабатываем возобнавление подключения
                                    if(empty($status)){// если нет ошибок
                                        // обновляем информацию о боте
                                        $data = array(// данные для отправки
                                            "op" => 3,// status update
                                            "d" => array(// data
                                                "since" => null,
                                                "game" => array(
                                                    "name" => $app["val"]["discordBotGame"],
                                                    "type" => 0
                                                ),
                                                "status" => "online",
                                                "afk" => false
                                            )
                                        );
                                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                        websocket_write($websocket, $data, true);
                                    };
                                    break;
                                case "TYPING_START":// typing start
                                    // обрабатываем начало набора сообщения
                                    if(isset($data["d"]["member"]["user"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        $flag = ($permission & $app["val"]["discordBotPermission"]) == $app["val"]["discordBotPermission"];
                                        if($flag){// если бот контролирует канал в котором пользователь набирает сообщение
                                            $member = $app["fun"]["setСache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                            if($member){// если удалось закешировать данные
                                                $user = $app["fun"]["setСache"]("user", $data["d"]["member"]["user"]);
                                                if($user){// если удалось закешировать данные
                                                    $app["fun"]["getСache"]("user", $data["d"]["member"]["user"]["id"]);
                                                }else $app["fun"]["delСache"]("user", $data["d"]["member"]["user"]["id"]);
                                            }else $app["fun"]["delСache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                        };
                                    };
                                    break;
                                case "GUILD_CREATE":// guild create
                                case "GUILD_UPDATE":// guild update
                                    // обрабатываем изменение гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $guild = $app["fun"]["setСache"]("guild", $data["d"]);
                                        if($guild){// если удалось закешировать данные
                                            // обрабатываем гильдию
                                            $flag = $app["method"]["discord.guild"](
                                                array(// параметры для метода
                                                    "guild" => $data["d"]["id"]
                                                ),
                                                array("nocontrol" => true),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                        }else $app["fun"]["delСache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_DELETE":// guild delete
                                    // обрабатываем удаление гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_MEMBER_ADD":// guild member add
                                case "GUILD_MEMBER_UPDATE":// guild member update
                                    // обрабатываем изменение участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $member = $app["fun"]["setСache"]("member", $data["d"], $data["d"]["guild_id"]);
                                        if($member){// если удалось закешировать данные
                                        }else $app["fun"]["delСache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "GUILD_MEMBER_REMOVE":// guild member remove
                                    // обрабатываем удаление участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "CHANNEL_CREATE":// channel create
                                case "CHANNEL_UPDATE":// channel update
                                    // обрабатываем изменение канала
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $channel = $app["fun"]["setСache"]("channel", $data["d"], $data["d"]["guild_id"], null);
                                        if($channel){// если удалось закешировать данные
                                            // обрабатываем канал
                                            $flag = $app["method"]["discord.channel"](
                                                array(// параметры для метода
                                                    "channel" => $data["d"]["id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array("nocontrol" => true),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                        }else $app["fun"]["delСache"]("channel", $data["d"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "CHANNEL_DELETE":// channel delete
                                    // обрабатываем удаление канала
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("channel", $data["d"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_CREATE":// message create
                                case "MESSAGE_UPDATE":// message update
                                    // обрабатываем изменение сообщения
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $message = $app["fun"]["setСache"]("message", $data["d"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        if($message){// если удалось закешировать данные
                                            // обрабатываем сообщение
                                            $flag = $app["method"]["discord.message"](
                                                array(// параметры для метода
                                                    "message" => $data["d"]["id"],
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array("nocontrol" => true),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                        }else $app["fun"]["delСache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_DELETE":// message delete
                                    // обрабатываем удаление сообщения
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_DELETE_BULK":// message delete bulk
                                    // обрабатываем удаление сообщения
                                    if(isset($data["d"]["ids"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        for($i = 0, $iLen = count($data["d"]["ids"]); $i < $iLen; $i++){// пробигаемся по идентификаторам сообщений
                                            $app["fun"]["delСache"]("message", $data["d"]["ids"][$i], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        };
                                    };
                                    break;
                            };
                            break;
                        case 7:// reconnect
                            // инициализируем новое подключение
                            if(websocket_check($websocket)) websocket_close($websocket);// закрываем старое подключение
                            $websocket = websocket_open($app["val"]["discordWebSocketHost"], 443, null, $error, 10, true);
                            if($websocket){// если удалось создать подключение к веб-сокету
                                $heartbeatInterval = 0;// отключаем проверку соединения
                            }else $status = 305;// не удалось установить соединение с удалённым сервером
                            break;
                        case 9:// invalid session
                            // пробуем создать новую сессию
                            $data = array(// данные для отправки
                                "op" => 2,// identify
                                "d" => array(// data
                                    "token" => $app["val"]["discordBotToken"],
                                    "properties" => array(
                                        '$browser' => "Bot Gateway Connect"
                                    )
                                )
                            );
                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                            websocket_write($websocket, $data, true);
                            break;
                        case 10:// hello
                            // обрабатываем приветствие
                            if(isset($data["d"]["heartbeat_interval"])){// если есть обязательное значение
                                $heartbeatSendTime = $now;
                                $heartbeatAcceptTime = $now;
                                $heartbeatInterval = $data["d"]["heartbeat_interval"] / 1000;
                                // пробуем авторизоваться по сессии
                                $data = array(// данные для отправки
                                    "op" => 6,// resume
                                    "d" => array(// data
                                        "token" => $app["val"]["discordBotToken"],
                                        "session_id" => $session->get("sid", "value"),
                                        "seq" => 1 * $session->get("seq", "value")
                                    )
                                );
                                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                websocket_write($websocket, $data, true);
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                            break;
                        case 11:// heartbeat ack
                            // обрабатываем ответ на серцебиение
                            $heartbeatAcceptTime = $now;
                            break;
                    };
                    // сохраняем номер уведомления
                    if(empty($status)){// если нет ошибок
                        if(get_val($data, "s", 0)){// если есть номер
                            if($session->set("seq", "value", $data["s"])){// если данные успешно добавлены
                                $isSessionUpdate = true;// были обновлены данные в базе данных
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    };
                    // проверяем и поддерживаем серцебиение
                    if(empty($status)){// если нет ошибок
                        if($heartbeatInterval > 0){// если задан интервал серцебиения
                            if($heartbeatSendTime - $heartbeatAcceptTime > $heartbeatInterval + 10){// если соединение зависло
                                // разрываем соединение
                                websocket_close($websocket);// закрываем старое подключение
                                $websocket = false;// подключение закрыто
                            }else if($now - $heartbeatSendTime > $heartbeatInterval){// если нужно отправить серцебиение
                                // отправляем серцебиение
                                $data = array(// данные для отправки
                                    "op" => 1,// heartbeat
                                    "d" => 1 * $session->get("seq", "value")
                                );
                                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                websocket_write($websocket, $data, true);
                                $heartbeatSendTime = $now;
                            };
                        };
                    };
                    // выполняем операции с базами данных
                    if(!(($index - 3) % $app["val"]["eventOperationCycle"])){// если пришло время
                        // выполняем регламентные операции с базой событий
                        if(empty($status)){// если нет ошибок
                            if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                                $items = $app["fun"]["changeEvents"]($now, $status);
                                $flag = count($items) > 0;// были ли изменения
                                $isEventsUpdate = $isEventsUpdate || $flag;
                                $counts = array();// счётчики для каналов
                                // определяем каналы для обновления уведомлений
                                foreach($items as $id => $item){// пробигаемся по значениям
                                    // создаём структуру счётчика
                                    $count = &$counts;// счётчик элиментов
                                    foreach(array($item["guild"], $item["channel"]) as $key){
                                        if(!isset($count[$key])) $count[$key] = array();
                                        $count = &$count[$key];// получаем ссылку
                                    };
                                    // выполняем подсчёт элиментов
                                    if(!isset($count["item"])) $count["item"] = 0;
                                    $count["item"]++;
                                };
                                // выполняем обновление уведомлений
                                foreach($counts as $gid => $items){// пробигаемся по гильдиям
                                    // получаем данные о гильдии
                                    if(!empty($status)) break;// не продолжаем при ошибке
                                    $guild = $app["fun"]["getСache"]("guild", $gid);
                                    if($guild){// если удалось получить данные
                                        foreach($items as $cid => $count){// пробигаемся по каналам
                                            // получаем данные о канале
                                            if(!empty($status)) break;// не продолжаем при ошибке
                                            $channel = $app["fun"]["getСache"]("channel", $cid, $gid, null);
                                            if($channel and $count["item"]){// если нужно выполнить
                                                // обрабатываем канал
                                                $flag = $app["method"]["discord.channel"](
                                                    array(// параметры для метода
                                                        "channel" => $cid,
                                                        "guild" => $gid
                                                    ),
                                                    array("nocontrol" => true),
                                                    $sign, $status
                                                );
                                                $isEventsUpdate = $isEventsUpdate || $flag;
                                            };
                                        };
                                    };
                                };
                            };
                        };
                        // переодически сохраняем базу данных событий
                        if(empty($status)){// если нет ошибок
                            if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                                if($isEventsUpdate){// если нужно сохранить
                                    if($events->save(true)){// если данные успешно сохранены
                                        $isEventsUpdate = false;// были сохранены данные в базе данных
                                    }else $status = 307;// не удалось сохранить базу данных
                                };
                            };
                        };
                        // переодически сохраняем базу данных сесии
                        if(empty($status)){// если нет ошибок
                            if($isSessionUpdate){// если нужно сохранить
                                if($session->save(true)){// если данные успешно сохранены
                                    $isSessionUpdate = false;// были сохранены данные в базе данных
                                }else $status = 307;// не удалось сохранить базу данных
                            };
                        };
                    };
                    // увеличиваем индексы
                    $index++;// индекс итераций цикла
                }while(// множественное условие
                    empty($status) and $index < $app["val"]["discordWebSocketLimit"]
                    and (!$websocket or websocket_check($websocket))
                );
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нет ошибок
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                if(empty($status) and $isSessionUpdate){// если нет ошибок
                    if($session->save(false)){// если данные успешно сохранены
                    }else $status = 307;// не удалось сохранить базу данных
                }else $session->unlock();// разблокируем базу
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        },
        "discord.guild" => function($params, $options, $sign, &$status){// обрабатываем гильдию
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $now = microtime(true);// текущее время
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($token) and !is_null($guild)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($token) and !empty($guild)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getСache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // выполняем обработку каналов в гильдии
            if(empty($status)){// если нет ошибок
                for($i = count($guild["channels"]) - 1; $i > -1 and empty($status); $i--){
                    if(isset($guild["channels"][$i])){// если очередной элимент существует
                        $channel = $guild["channels"][$i];// получаем очередной элимент
                        // обрабатываем канал
                        $flag = $app["method"]["discord.channel"](
                            array(// параметры для метода
                                "channel" => $channel["id"],
                                "guild" => $guild["id"]
                            ),
                            array("nocontrol" => true),
                            $sign, $status
                        );
                        $isEventsUpdate = $isEventsUpdate || $flag;
                    };
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нет ошибок
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        },
        "discord.channel" => function($params, $options, $sign, &$status){// обрабатываем канал
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $now = microtime(true);// текущее время
            $hasPermission = false;// есть разрешения
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($token) and !is_null($guild) and !is_null($channel)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($token) and !empty($guild) and !empty($channel)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getСache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"], null);
                if($channel){// если удалось получить данные
                    // проверяем разрешения
                    $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                    $hasPermission = ($permission & $app["val"]["discordBotPermission"]) == $app["val"]["discordBotPermission"];
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // очищаем устаревшие записи событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                for($i = $events->length - 1; $i >= 0 and empty($status); $i--){
                    $event = $events->get($events->key($i));
                    if(// множественное условие
                        $event["time"] < $now - $app["val"]["eventTimeDelete"]
                        or $event["time"] > $now + $app["val"]["eventTimeAdd"]
                    ){// если нужно удалить эту запись
                        if($events->set($events->key($i))){// если данные успешно добавлены
                            $isEventsUpdate = true;// были обновлены данные в базе данных
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // выполняем обработку сообщений в канале
            if(empty($status) and $hasPermission){// если нужно выполнить
                for($i = count($channel["messages"]) - 1; $i > -1 and empty($status); $i--){
                    if(isset($channel["messages"][$i])){// если очередной элимент существует
                        $message = $channel["messages"][$i];// получаем очередной элимент
                        // обрабатываем сообшение
                        $flag = $app["method"]["discord.message"](
                            array(// параметры для метода
                                "message" => $message["id"],
                                "channel" => $channel["id"],
                                "guild" => $guild["id"]
                            ),
                            array("nocontrol" => true),
                            $sign, $status
                        );
                        $isEventsUpdate = $isEventsUpdate || $flag;
                    };
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нет ошибок
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        },
        "discord.message" => function($params, $options, $sign, &$status){// обрабатываем сообщение
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $error = 0;// код ошибки для обратной связи
            $hasPermission = false;// есть разрешения
            $now = microtime(true);// текущее время
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $message = $app["fun"]["getClearParam"]($params, "message", "string");
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($token) and !is_null($guild) and !is_null($channel) and !is_null($message)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($token) and !empty($guild) and !empty($channel) and !empty($message)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]("raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $types = $app["fun"]["getStorage"]("types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]("roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $actions = $app["fun"]["getStorage"]("actions", false);
                if(!empty($actions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $additions = $app["fun"]["getStorage"]("additions", false);
                if(!empty($additions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $dates = $app["fun"]["getStorage"]("dates", false);
                if(!empty($dates)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $feedbacks = $app["fun"]["getStorage"]("feedbacks", false);
                if(!empty($feedbacks)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getСache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"], null);
                if($channel){// если удалось получить данные
                    // проверяем разрешения
                    $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                    $hasPermission = ($permission & $app["val"]["discordBotPermission"]) == $app["val"]["discordBotPermission"];
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о сообщении
            if(empty($status) and $hasPermission){// если нужно выполнить
                $message = $app["fun"]["getСache"]("message", $message, $channel["id"], $guild["id"]);
                if($message){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // обрабатываем сообщение
            if(empty($status) and $hasPermission){// если нужно выполнить
                if(// множественное условие
                    !$message["pinned"] and !$message["type"]
                    and $message["author"]["id"] != $app["val"]["discordBotId"]
                ){// если это сообщение с командой
                    $command = array(// пустая команда
                        "action" => "",     // действие
                        "role" => "",       // роль
                        "date" => 0,        // дата
                        "time" => 0,        // дата и время
                        "raid" => "",       // рейд
                        "user" => "",       // пользователь
                        "addition" => "",   // опция
                        "comment" => ""     // комментарий
                    );
                    // определяем команду в сообщении
                    if(empty($error)){// если нет проблем
                        $content = $message["content"];// получаем содержимое сообщения
                        $userPattern = "/^<@!?(\d+)>$/";// шаблон указания пользователя
                        $goToUserSet = false;// перескочить к указанию пользователя
                        $delim = " ";// разделитель параметров в сообщении
                        /////////////////////////////////////////////////////////////////// - действие
                        $value = $app["fun"]["getNextValue"]($content, $delim);
                        $key = $app["fun"]["getItemKey"]($actions, $value, true, true);
                        if(mb_strlen($key)) $command["action"] = $key;
                        /////////////////////////////////////////////////////////////////// - роль
                        if(!mb_strlen($value)) $value = $app["fun"]["getNextValue"]($content, $delim);
                        $key = $app["fun"]["getItemKey"]($roles, $value, false, true);
                        if(mb_strlen($key)) $command["role"] = $key;
                        /////////////////////////////////////////////////////////////////// - дата
                        if(!$goToUserSet){// если нужно выполнить обработку этого параметра
                            $text = $content;// сохраняем текущее состояния строки для пропуска параметра
                            $value = $app["fun"]["getNextValue"]($content, $delim);
                            $key = $app["fun"]["getItemKey"]($dates, $value, false, true);
                            $t1 = strtotime($key, $message["timestamp"]);
                            $time = 0;// вспомогательная переменная для обработки времени
                            if(preg_match("/^(\d{1,2}\.)(\d{2})$/", $key, $list)){// если указано число и месяц
                                $t1 = strtotime($key . "." . date("Y", $message["timestamp"]), $message["timestamp"]);
                                $t2 = strtotime($key . "." . date("Y", strtotime("+1 year", $message["timestamp"])), $message["timestamp"]);
                                $time = (abs($t1 - $message["timestamp"]) < abs($t2 - $message["timestamp"]) ? $t1 : $t2);
                            }else if(preg_match("/^(\d{1,2}\.)(\d{2}\.)(\d{2})$/", $key, $list)){// если указано число, месяц и кратко год
                                $time = strtotime($list[1] . $list[2] . mb_substr(date("Y", $message["timestamp"]), 0, 2) . $list[3], $message["timestamp"]);
                            }else if(preg_match($userPattern, $key, $list)){// если указан пользователь
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($app["fun"]["getItemKey"]($additions, $key, false, false))){// если указана опция
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($key) and $t1 <= 0){// если указано не время
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($key)) $time = $t1;
                            if(false === $time) $time = -1;// приводим к единобразному написанию
                            if(!$goToUserSet and $time) $command["date"] = $time;
                        };
                        /////////////////////////////////////////////////////////////////// - время
                        if(!$goToUserSet){// если нужно выполнить обработку этого параметра
                            $text = $content;// сохраняем текущее состояния строки для пропуска параметра
                            if(!mb_strlen($value)) $value = $app["fun"]["getNextValue"]($content, $delim);
                            $key = $value;// копируем значение в ключ для дальнейшей обработки
                            $value = "";// сбрасываем значение для корректной обработки следующего параметра
                            $t1 = strtotime(date("d.m.Y", $time) . $delim . $key, $message["timestamp"]);
                            $time = 0;// вспомогательная переменная для обработки времени
                            if(preg_match($userPattern, $key, $list)){// если указан пользователь
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($app["fun"]["getItemKey"]($additions, $key, false, false))){// если указана опция
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($key) and $t1 <= 0){// если указано не время
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($key)) $time = $t1;
                            if(false === $time) $time = -1;// приводим к единобразному написанию
                            $offset = 1 * date("Z");// определяем текущее смещение временной зоны
                            if($time > 0) $time = ceil(($time - $offset) / $app["val"]["eventTimeStep"]) * $app["val"]["eventTimeStep"] + $offset;
                            if(!$goToUserSet and $time) $command["time"] = $time;
                        };
                        /////////////////////////////////////////////////////////////////// - рейд
                        if(!$goToUserSet){// если нужно выполнить обработку этого параметра
                            $text = $content;// сохраняем текущее состояния строки для пропуска параметра
                            if(!mb_strlen($value)) $value = $app["fun"]["getNextValue"]($content, $delim);
                            $key = $app["fun"]["getItemKey"]($raids, $value, false, true);
                            if(preg_match($userPattern, $key, $list)){// если указан пользователь
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($app["fun"]["getItemKey"]($additions, $key, false, false))){// если указана опция
                                $content = $text;// восстанавливаем сохранённое состояние строки
                                $goToUserSet = true;// перескочить к указанию пользователя
                            }else if(mb_strlen($key)){// если есть данные для обработки
                                if(1 == mb_strlen($key)){// если пользователь отделил первый символ
                                    $value = $key . $app["fun"]["getNextValue"]($content, $delim);
                                    $key = $app["fun"]["getItemKey"]($raids, $value, false, true);
                                };
                                $command["raid"] = $key;
                            };
                        };
                        /////////////////////////////////////////////////////////////////// - пользователь
                        $text = $content;// сохраняем текущее состояния строки для пропуска параметра
                        if(!mb_strlen($value)) $value = $app["fun"]["getNextValue"]($content, $delim);
                        $key = $value;// копируем значение в ключ для дальнейшей обработки
                        $value = "";// сбрасываем значение для корректной обработки следующего параметра
                        if(preg_match($userPattern, $key, $list)) $command["user"] = $list[1];
                        else $content = $text;// восстанавливаем сохранённое состояние строки
                        /////////////////////////////////////////////////////////////////// - опция
                        if(!mb_strlen($value)) $value = $app["fun"]["getNextValue"]($content, $delim);
                        $key = $app["fun"]["getItemKey"]($additions, $value, false, true);
                        if(mb_strlen($key)) $command["addition"] = $key;
                        /////////////////////////////////////////////////////////////////// - комментарий
                        $delim = "\n";// разделитель
                        if(!mb_strlen($value)) $value = $app["fun"]["getNextValue"]($content, $delim);
                        $key = trim($value);// копируем значение в ключ для дальнейшей обработки
                        $key = mb_strtoupper(mb_substr($key, 0, 1)) . mb_substr($key, 1);
                        $value = "";// сбрасываем значение для корректной обработки следующего параметра
                        if(mb_strlen($key)) $command["comment"] = $key;
                    };
                    // обрабатываем команду в сообщении
                    if(empty($error)){// если нет проблем
                        switch($command["action"]){// поддержмваемые комманды
                            case "add":// добавить запись
                                // проверяем что указана роль
                                if(empty($error)){// если нет проблем
                                    if(!empty($command["role"])){// если проверка пройдена
                                    }else $error = 2;
                                };
                                // проверяем ограничения на комментарий
                                if(empty($error)){// если нет проблем
                                    $value = $app["val"]["eventCommentLength"];
                                    if(mb_strlen($command["comment"]) <= $value){// если проверка пройдена
                                    }else $error = 3;
                                };
                                // считаем записи и задамём значения по умолчанию
                                if(empty($error)){// если нет проблем
                                    $counts = array();// счётчики элиментов
                                    // считаем количество записей
                                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                        $event = $events->get($id);// получаем элимент по идентификатору
                                        $time = strtotime(date("d.m.Y", $event["time"]));
                                        if(// множественное условие
                                            $event["channel"] == $channel["id"]
                                            and $event["guild"] == $guild["id"]
                                            and (!empty($command["raid"]) ? $command["raid"] == $event["raid"] : true)
                                            and (!empty($command["time"]) ? $command["time"] == $event["time"] : true)
                                            and (!empty($command["date"]) ? $command["date"] == $time : true)
                                        ){// если нужно посчитать счётчик
                                            // создаём структуру счётчика
                                            $count = &$counts;// счётчик элиментов
                                            foreach(array($event["time"], $event["raid"]) as $key){
                                                if(!isset($count[$key])) $count[$key] = array();
                                                $count = &$count[$key];// получаем ссылку
                                            };
                                            // выполняем подсчёт элиментов
                                            if(!isset($count["item"])) $count["item"] = 0;
                                            $count["item"]++;
                                            // сохраняем исходный элимент
                                            $item = $event;
                                        };
                                    };
                                    // считаем количество рейдов
                                    $index = 0;// количество рейдов
                                    foreach($counts as $items){
                                        foreach($items as $count){
                                            $index++;
                                        };
                                    };
                                    // задаём значения по умолчанию
                                    if(1 == $index){// если в канале один рейд
                                        $flag = true;// если значение ещё не задано
                                        // задаём значение рейда по умолчанию
                                        $flag = ($flag and empty($command["raid"]));
                                        if($flag) $command["raid"] = $item["raid"];
                                        // задаём значение времени по умолчанию
                                        $time = strtotime(date("d.m.Y", $item["time"]));
                                        $flag = ($flag and empty($command["time"]));
                                        if($flag) $command["time"] = $item["time"];
                                        if($flag) $command["date"] = $time;
                                    };
                                };
                                // проверяем что указано время
                                if(empty($error)){// если нет проблем
                                    if(// множественное условие
                                        !empty($command["date"])
                                        and !empty($command["time"])
                                    ){// если проверка пройдена
                                    }else $error = 4;
                                };
                                // проверяем корректность указания времени
                                if(empty($error)){// если нет проблем
                                    if(// множественное условие
                                        $command["date"] > 0
                                        and $command["time"] > 0
                                    ){// если проверка пройдена
                                    }else $error = 5;
                                };
                                // проверяем что указан рейд
                                if(empty($error)){// если нет проблем
                                    if(!empty($command["raid"])){// если проверка пройдена
                                    }else $error = 6;
                                };
                                // проверяем ограничения по времени записи
                                if(empty($error)){// если нет проблем
                                    $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                    $flag = ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"];
                                    if(// множественное условие
                                        ($flag or $command["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"])
                                        and $command["time"] > $message["timestamp"] - $app["val"]["eventTimeDelete"]
                                        and $command["time"] < $message["timestamp"] + $app["val"]["eventTimeAdd"]
                                    ){// если проверка пройдена
                                    }else $error = 7;
                                };
                                // проверяем корректность указания игровой роли
                                if(empty($error)){// если нет проблем
                                    $role = $roles->get($command["role"]);
                                    if(!empty($role)){// если проверка пройдена
                                    }else $error = 8;
                                };
                                // проверяем корректность указания рейда
                                if(empty($error)){// если нет проблем
                                    $raid = $raids->get($command["raid"]);
                                    if(!empty($raid)){// если проверка пройдена
                                    }else $error = 9;
                                };
                                // проверяем возможность использовать роль в рейде
                                if(empty($error)){// если нет проблем
                                    $limit = $raid[$role["key"]];
                                    if($limit > -1){// если проверка пройдена
                                    }else $error = 10;
                                };
                                // проверяем ограничивающий фильтр в имени канала
                                if(empty($error)){// если нет проблем
                                    $count = array();// счётчик элиментов
                                    // считаем количество записей
                                    for($i = 0, $iLen = $types->length; $i < $iLen; $i++){
                                        $key = $types->key($i);// получаем ключевой идентификатор по индексу
                                        $item = $types->get($key);// получаем элимент по идентификатору
                                        if(preg_match($item["filter"], $channel["name"], $list)){// если есть совподение
                                            // выполняем подсчёт элиментов
                                            if(!isset($count["raid"])) $count["raid"] = 0;
                                            if(!isset($count["channel"])) $count["channel"] = 0;
                                            if($item["key"] == $raid["type"]) $count["raid"]++;
                                            $count["channel"]++;
                                        };
                                    };
                                    if(// множественное условие
                                        empty($count["channel"])
                                        or !empty($count["raid"])
                                    ){// если проверка пройдена
                                    }else $error = 11;
                                };
                                // определяем контекст пользователя
                                if(empty($error)){// если нет проблем
                                    if(!empty($command["user"])) $uid = $command["user"];
                                    else $uid = $message["author"]["id"];
                                    $member = $app["fun"]["getСache"]("member", $uid, $guild["id"]);
                                    if($member and !$member["user"]["bot"]){// если проверка пройдена
                                    }else $error = 12;
                                };
                                // считаем записи и проверяем лимиты
                                if(empty($error)){// если нет проблем
                                    $count = array();// счётчик элиментов
                                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                        $event = $events->get($id);// получаем элимент по идентификатору
                                        // выполняем подсчёт элиментов
                                        if(!isset($count["time"])) $count["time"] = 0;
                                        if(!isset($count["raid"])) $count["raid"] = 0;
                                        if(!isset($count["item"])) $count["item"] = 0;
                                        if(// множественное условие
                                            $event["channel"] == $channel["id"]
                                            and $event["guild"] == $guild["id"]
                                        ){// если нужно посчитать счётчик
                                            // в разрезе колличества записей
                                            if(// множественное условие
                                                $event["raid"] == $raid["key"]
                                                and $event["time"] == $command["time"]
                                            ){// если рейд и время совпадает
                                                $count["item"]++;
                                            };
                                            // в разрезе пользователя
                                            if(// множественное условие
                                                $event["user"] == $member["user"]["id"]
                                                and $event["time"] == $command["time"]
                                            ){// если пользователь и время совпадает
                                                $count["time"]++;
                                                if($event["raid"] == $raid["key"]){// если рейд совпадает
                                                    $count["raid"]++;
                                                };
                                            };
                                        };
                                    };
                                    if(// множественное условие
                                        $count["raid"] < $app["val"]["eventRaidLimit"]
                                        and $count["time"] < $app["val"]["eventTimeLimit"]
                                    ){// если проверка пройдена
                                    }else $error = 13;
                                };
                                // проверяем права на создание записи от других пользователей
                                if(empty($error) and $member["user"]["id"] != $message["author"]["id"]){// если нужно выполнить
                                    $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                    $flag = ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"];
                                    if($flag){// если проверка пройдена
                                    }else $error = 14;
                                };
                                // проверяем права на создание первой записи
                                if(empty($error) and empty($count["item"])){// если нужно выполнить
                                    $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                    $flag = ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"];
                                    if($flag){// если проверка пройдена
                                    }else $error = 15;
                                };
                                // формируем элимент события
                                if(empty($error)){// если нет проблем
                                    $id = $events->length ? $events->key($events->length - 1) + 1 : 1;
                                    $event = array(// новая запись
                                        "guild" => $guild["id"],
                                        "channel" => $channel["id"],
                                        "user" => $member["user"]["id"],
                                        "comment" => $command["comment"],
                                        "time" => $command["time"],
                                        "raid" => $raid["key"],
                                        "role" => $role["key"],
                                        "leader" => false,
                                        "repeat" => 0
                                    );
                                };
                                // обрабатываем дополнительные опции
                                if(empty($error)){// если нет проблем
                                    $value = 24*60*60;// база для повторения
                                    switch($command["addition"]){// поддержмваемые опции
                                        case "weekly"://еженедельно
                                            $value = 7 * $value;
                                        case "daily"://ежедневно
                                            $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                            $flag = ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"];
                                            if($flag){// если проверка пройдена
                                                $event["repeat"] = $value;
                                            }else $error = 16;
                                        case "leader":// лидер
                                            $event["leader"] = true;
                                            break;
                                        case "note":// заметка
                                        case "":// опция не указана
                                            break;
                                        default:// не известная опция
                                            $error = 17;
                                    };
                                };
                                // добавляем данные в базу данных
                                if(empty($error)){// если нет проблем
                                    if($events->set($id, null, $event)){// если данные успешно добавлены
                                        $isEventsUpdate = true;// были обновлены данные в базе данных
                                    }else $status = 309;// не удалось записать данные в базу данных
                                };
                                break;
                            case "remove":// удалить запись
                                // проверяем корректность указания времени
                                if(empty($error)){// если нет проблем
                                    if(// множественное условие
                                        ($command["date"] > 0 or empty($command["date"]))
                                        and ($command["time"] > 0 or empty($command["time"]))
                                    ){// если проверка пройдена
                                    }else $error = 18;
                                };
                                // проверяем ограничения по времени записи
                                if(empty($error)){// если нет проблем
                                    $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                    $flag = ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"];
                                    if(// множественное условие
                                        ($flag or $command["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"])
                                        or (empty($command["date"]) or empty($command["time"]))
                                    ){// если проверка пройдена
                                    }else $error = 19;
                                };    
                                // проверяем корректность указания игровой роли
                                if(empty($error)){// если нет проблем
                                    $role = $roles->get($command["role"]);
                                    if(// множественное условие
                                        empty($command["role"])
                                        or !empty($role)
                                    ){// если проверка пройдена
                                    }else $error = 20;
                                };
                                // проверяем корректность указания рейда
                                if(empty($error)){// если нет проблем
                                    $raid = $raids->get($command["raid"]);
                                    if(// множественное условие
                                        empty($command["raid"])
                                        or !empty($raid)
                                    ){// если проверка пройдена
                                    }else $error = 21;
                                };
                                // определяем контекст пользователя
                                if(empty($error)){// если нет проблем
                                    if(!empty($command["user"])) $uid = $command["user"];
                                    else $uid = $message["author"]["id"];
                                    $user = $app["fun"]["getСache"]("user", $uid);
                                    if($user and !$user["bot"]){// если проверка пройдена
                                    }else $error = 22;
                                };
                                // проверяем права на удаление записей других пользователей
                                if(empty($error) and $user["id"] != $message["author"]["id"]){// если нужно выполнить
                                    $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                    $flag = ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"];
                                    if($flag){// если проверка пройдена
                                    }else $error = 23;
                                };
                                // удаляем записи событий
                                if(empty($error)){// если нет проблем
                                    $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                    $flag = ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"];
                                    for($i = $events->length - 1; $i > - 1 and empty($status); $i--){
                                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                        $event = $events->get($id);// получаем элимент по идентификатору
                                        $time = strtotime(date("d.m.Y", $event["time"]));
                                        if(// множественное условие
                                            $event["channel"] == $channel["id"]
                                            and $event["guild"] == $guild["id"]
                                            and $event["user"] == $user["id"]
                                            and (empty($command["time"]) or $command["time"] == $event["time"])
                                            and (empty($command["date"]) or $command["date"] == $time)
                                            and (empty($role) or $event["role"] == $role["key"])
                                            and (empty($raid) or $event["raid"] == $raid["key"])
                                            and ($flag or $event["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"])
                                        ){// если нужно удалить запись из событий
                                            if($events->set($id)){// если данные успешно удалены
                                                $isEventsUpdate = true;// были обновлены данные в базе данных
                                            }else $status = 309;// не удалось записать данные в базу данных
                                        };
                                    };
                                };
                                break;
                            default:// не известная команда
                                $error = 1;
                        };
                    };
                    // информируем пользователя
                    if(!empty($error)){// если есть проблема
                        // готовим контент для личного сообщения
                        if(empty($status)){// если нет ошибок
                            $feedback = $feedbacks->get($error);// получаем элимент по идентификатору
                            if(!empty($feedback)) $content = template($feedback["content"], $command);
                            if(empty($content)) $content = "Ваше сообщение не обработано из-за непредвиденной проблемы.";
                        };
                        // получаем идентификатор личного канала
                        if(empty($status)){// если нет ошибок
                            $user = $app["fun"]["getСache"]("user", $message["author"]["id"]);
                            if($user and !$user["bot"] and isset($user["channels"][0])){// если личный канал существует
                                $item = $user["channels"][0];// получаем очередной элимент
                                // отправляем личное сообщение
                                $uri = "/channels/" . $item["id"] . "/messages";
                                $data = array("content" => $content);
                                $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                                if(200 == $code or 403 == $code){// если запрос выполнен успешно
                                }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                            };
                        };
                    };
                };
            };
            // удаляем сообщение
            if(empty($status) and $hasPermission){// если нужно выполнить
                if(// множественное условие
                    !$message["pinned"]
                    and $message["author"]["id"] != $app["val"]["discordBotId"]
                ){// если это сообщение можно удалить
                    $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                    $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                    if(204 == $code or 404 == $code){// если запрос выполнен успешно
                        $app["fun"]["delСache"]("message", $message["id"], $channel["id"], $guild["id"]);
                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                };
            };
            // формируем текст для уведомлений
            if(empty($status) and $hasPermission){// если нужно выполнить
                $delim = "\n";// разделитель строк
                $all = "";// идентификатор всех ролей
                $any = 0;// идентификатор любой группы
                $lines = array();// список строк
                $blocks = array();// список блоков
                $contents = array();// контент для сообщений
                // формируем список записей для отображения
                $limits = array();// лимиты для рейдов
                $counts = array();// счётчики для групп
                $leaders = array();// лидеры для групп
                $comments = array();// комментарии для групп
                $repeats = array();// повторяемость события
                $items = array();// список записей событий
                $index = 0;// индекс элимента в новом массиве
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $item = $events->get($id);// получаем элимент по идентификатору
                    $unit = $events->get($id);// получаем элимент по идентификатору
                    $raid = $raids->get($item["raid"]);
                    $limit = $raid[$item["role"]];
                    if(// множественное условие
                        $item["channel"] == $channel["id"]
                        and $item["guild"] == $guild["id"]
                        and $limit > -1
                    ){// если нужно включить запись в уведомление
                        // создаём структуру счётчика
                        $count = &$counts;// счётчик элиментов
                        foreach(array($item["time"], $item["raid"], $any) as $key){
                            if(!isset($count[$key])) $count[$key] = array();
                            $count = &$count[$key];// получаем ссылку
                        };
                        // считаем без учётом группы
                        if(!isset($count[$item["role"]])) $count[$item["role"]] = 0;
                        if(!isset($count[$all])) $count[$all] = 0;
                        $count[$item["role"]]++;
                        $count[$all]++;
                        // вычисляем лимит
                        if(!isset($limits[$item["raid"]])){// если нужно вычислить общий лимит
                            $limits[$item["raid"]] = 0;// начальное значение лимита
                            for($limit = 1, $j = 0, $jLen = $roles->length; $j < $jLen and $limit; $j++){
                                $role = $roles->get($roles->key($j));// получаем очередную роль
                                $limit = $raid[$role["key"]];// получаем значение лимита
                                if($limit > 0) $limits[$item["raid"]] += $limit;
                                if(!$limit) $limits[$item["raid"]] = 0;
                            };
                        };
                        // определяем группу
                        $limit = $raid[$item["role"]];
                        $group = $limit ? ceil($count[$item["role"]] / $limit) : 1;
                        // создаём структуру счётчика
                        $count = &$counts;// счётчик элиментов
                        foreach(array($item["time"], $item["raid"], $group) as $key){
                            if(!isset($count[$key])) $count[$key] = array();
                            $count = &$count[$key];// получаем ссылку
                        };
                        // считаем с учётом группы
                        if(!isset($count[$item["role"]])) $count[$item["role"]] = 0;
                        if(!isset($count[$all])) $count[$all] = 0;
                        $count[$item["role"]]++;
                        $count[$all]++;
                        // создаём структуру лидера
                        $leader = &$leaders;// лидер по группам
                        foreach(array($item["time"], $item["raid"]) as $key){
                            if(!isset($leader[$key])) $leader[$key] = array();
                            $leader = &$leader[$key];// получаем ссылку
                        };
                        // определяем лидера группы
                        if(!isset($leader[$group])) $leader[$group] = $index;
                        $limit = $limits[$item["raid"]];// получаем значение лимита
                        if($index != $leader[$group]){// если лидер не текущий элимент
                            if(!$items[$leader[$group]]["leader"]){// если лидер выбран системой
                                if($item["leader"]) $leader[$group] = $index;
                                else if($count[$all] == $limit) $items[$leader[$group]]["leader"] = true;
                            }else if($item["leader"]) $item["leader"] = false;
                        }else if($count[$all] == $limit) $item["leader"] = true;
                        // создаём структуру комментария
                        $comment = &$comments;// комментарий по группам
                        foreach(array($item["time"], $item["raid"]) as $key){
                            if(!isset($comment[$key])) $comment[$key] = array();
                            $comment = &$comment[$key];// получаем ссылку
                        };
                        // определяем комментарий группы
                        if(!isset($comment[$any])) $comment[$any] = "";
                        if(!isset($comment[$group])) $comment[$group] = "";
                        if($item["leader"] and $unit["leader"]) $comment[$group] = $item["comment"];
                        if(1 == $group) $comment[$any] = $comment[$group];
                        // создаём структуру повторения события
                        $repeat = &$repeats;// повторяемость по группам
                        foreach(array($item["time"], $item["raid"]) as $key){
                            if(!isset($repeat[$key])) $repeat[$key] = array();
                            $repeat = &$repeat[$key];// получаем ссылку
                        };
                        // определяем повторяемость события
                        if(!isset($repeat[$any])) $repeat[$any] = false;
                        if(!isset($repeat[$group])) $repeat[$group] = false;
                        if($item["repeat"]) $repeat[$any] = true;
                        if($item["repeat"]) $repeat[$group] = true;
                        // расширяем свойства элимента
                        $days = array("Воскресенье", "Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота");
                        $months = array("", "Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря");
                        $item["title"] = date("d", $item["time"]) . " " . $months[date("n", $item["time"])];
                        $item["day"] = $days[date("w", $item["time"])];
                        $item["group"] = $group;
                        // сохраняем элимент в массив
                        $items[$index] = $item;
                        $index++;
                    };
                };
                // сортируем список записей для отображения
                usort($items, function($a, $b){// сортировка
                    $value = 0;// начальное значение
                    if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                    if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                    if(!$value and $a["group"] != $b["group"]) $value = $a["group"] > $b["group"] ? 1 : -1;
                    if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
                // формируем контент для уведомлений
                $before = null;// предыдущий элимент
                $mLen = 0;// длина текущего уведомления
                $bLen = 0;// длина текущего блока
                $line = "";// сбрасываем значение строки
                $position = 0;// позиция пользователя в рейде
                if(count($items)){// если есть элименты для отображения
                    for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                        $item = $items[$i];// получаем очередной элимент
                        $raid = $raids->get($item["raid"]);
                        $role = $roles->get($item["role"]);
                        $type = $types->get($raid["type"]);
                        $limit = $limits[$item["raid"]];
                        $group = $item["group"];
                        // построчно формируем текст содержимого
                        if(// множественное условие
                            empty($before)
                            or $before["title"] != $item["title"]
                        ){// если нужно добавить информацию о дате
                            // формируем блок и контент    
                            $bLen = 0;// длина текущего блока
                            $flag = count($lines);
                            if($flag){// если есть строка данных
                                $line = "_ _";// пустая строка
                                array_push($lines, $line);
                            };
                            if($flag){// если сформирован блок
                                if($mLen) $mLen += mb_strlen($delim);
                                $block = implode($delim, $lines);
                                $bLen = mb_strlen($block);
                            };
                            if(// множественное условие
                                count($blocks)
                                and $mLen + $bLen > $app["val"]["discordMessageLength"]
                            ){// если сформирован контент
                                $content = implode($delim, $blocks);
                                array_push($contents, $content);
                                $blocks = array();
                                $mLen = 0;
                            };
                            if($flag){// если сформирован блок
                                array_push($blocks, $block);
                                $mLen += $bLen;
                                $lines = array();
                            };
                            // формируем строки данных
                            $line = "**```" . $item["title"] . " - " . $item["day"] . "```**";
                            array_push($lines, $line);
                        };
                        if(// множественное условие
                            empty($before)
                            or $before["group"] != $group
                            or $before["time"] != $item["time"]
                            or $before["raid"] != $item["raid"]
                        ){// если нужно добавить информацию о времени
                            // получаем счётчик из структуру
                            $count = &$counts;// счётчик элиментов
                            foreach(array($item["time"], $item["raid"], $group) as $key){
                                $count = &$count[$key];// получаем ссылку
                            };
                            // получаем комментарий из структуру
                            $comment = &$comments;// комментарий по группам
                            foreach(array($item["time"], $item["raid"]) as $key){
                                $comment = &$comment[$key];// получаем ссылку
                            };
                            // получаем повторяемость из структуру
                            $repeat = &$repeats;// повторяемость по группам
                            foreach(array($item["time"], $item["raid"]) as $key){
                                $repeat = &$repeat[$key];// получаем ссылку
                            };
                            // формируем блок и контент    
                            $bLen = 0;// длина текущего блока
                            $flag = (count($lines) and $before["title"] == $item["title"]);
                            $flag = ($flag and (1 == $group or $limit and $count[$all] == $limit));
                            if($flag){// если есть строка данных
                                $line = "_ _";// пустая строка
                                array_push($lines, $line);
                            };
                            if($flag){// если сформирован блок
                                if($mLen) $mLen += mb_strlen($delim);
                                $block = implode($delim, $lines);
                                $bLen = mb_strlen($block);
                            };
                            if(// множественное условие
                                count($blocks)
                                and $mLen + $bLen > $app["val"]["discordMessageLength"]
                            ){// если сформирован контент
                                $content = implode($delim, $blocks);
                                array_push($contents, $content);
                                $blocks = array();
                                $mLen = 0;
                            };
                            if($flag){// если сформирован блок
                                array_push($blocks, $block);
                                $mLen += $bLen;
                                $lines = array();
                            };
                            // формируем строки данных
                            $flag = (1 == $group or $limit and $count[$all] == $limit);
                            if($flag){// если это основная или другая полная группа
                                $icon = ($limit and $count[$all] < $limit) ? $type["processing"] : $type["icon"];
                                $line = (!empty($icon) ? $icon . " " : "");
                                $line .= "**" . date("H:i", $item["time"]) . "** - **" . $raid["key"] . "** " . $raid["name"];
                                $line .= (!empty($raid["chapter"]) ? " **DLC**" : "") . ($limit ? " (" . $count[$all] . " из " . $limit . ")" : "");
                                $line .= ((1 == $group and $repeat[$any]) ? "  :repeat:" : "");
                                array_push($lines, $line);
                                $position = 1;
                            }else if(2 == $group or $before["count"] == $limit){// если это не полная группа
                                $line = " __Резерв:__";
                                array_push($lines, $line);
                            };
                            if($flag and !empty($comment[$any])){// если есть комментарий
                                $line = " __Комментарий__: " . $comment[$any];
                                array_push($lines, $line);
                            };
                        };
                        // формируем строки данных
                        $line = " **" . str_pad($position, 2, "0", STR_PAD_LEFT) . "** - " . $role["name"] . ": <@!" . $item["user"] . ">";
                        $key = "лидер";// идентификатор обозначающий лидера
                        $value = $item["comment"];// комментарий для обработки
                        for($j = -1, $jLen = mb_strlen($key); $j !== false; $j = mb_stripos($value, $key)){
                            if($j > -1) $value = mb_substr($value, 0,  $j) .  mb_substr($value, $j + $jLen);
                        };
                        $value = mb_strtolower(mb_substr($value, 0, 1)) . mb_substr($value, 1);
                        $value = trim(mb_substr($value, 0, $app["val"]["eventNoteLength"]));
                        $flag = (1 == $group or $limit and $count[$all] == $limit);
                        if($item["leader"] and $flag) $line .= " - " . $key;
                        else if($value) $line .= " - " . $value;
                        array_push($lines, $line);
                        $position++;
                        // сохраняем предыдущий элимент
                        $before = $item;// копируем значение
                        $before["count"] = $count[$all];
                    };
                }else{// если нет не одной записи
                    $line = "Ещё никто не записался.";
                    array_push($lines, $line);
                };
                // формируем блок и контент    
                $bLen = 0;// длина текущего блока
                $flag = count($lines);
                if($flag and count($items)){// если нужно выполнить
                    $line = "_ _";// пустая строка
                    array_push($lines, $line);
                };
                if($flag){// если сформирован блок
                    if($mLen) $mLen += mb_strlen($delim);
                    $block = implode($delim, $lines);
                    $bLen = mb_strlen($block);
                };
                if(// множественное условие
                    count($blocks)
                    and $mLen + $bLen > $app["val"]["discordMessageLength"]
                ){// если сформирован контент
                    $content = implode($delim, $blocks);
                    array_push($contents, $content);
                    $blocks = array();
                    $mLen = 0;
                };
                if($flag){// если сформирован блок
                    array_push($blocks, $block);
                    $mLen += $bLen;
                    $lines = array();
                };
                // формируем контент
                if(// множественное условие
                    count($blocks)
                ){// если сформирован контент
                    $content = implode($delim, $blocks);
                    array_push($contents, $content);
                    $blocks = array();
                    $mLen = 0;
                };
            };
            // обрабатываем все сообщения бота
            if(empty($status) and $hasPermission){// если нужно выполнить
                $items = array();// массив сообщений расписания
                // формируем список контентных сообщений бота и удаляем прочие сообщения бота
                for($i = count($channel["messages"]) - 1; $i > -1 and empty($status); $i--){
                    $item = $channel["messages"][$i];// получаем очередной элимент
                    if($item["author"]["id"] == $app["val"]["discordBotId"]){// если это сообщение бота
                        // удаляем сообщение
                        if($item["type"]){// если это не контентное сообщение
                            $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                            $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                            if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                $app["fun"]["delСache"]("message", $message["id"], $channel["id"], $guild["id"]);
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        }else array_unshift($items, $item);// добавляем сообщение в список
                    };
                };
                // удаляем лишнии сообщения из сформированного списка
                for($i = count($items) - 1; $i > count($contents) - 1 and empty($status); $i--){
                    $item = $items[$i];// получаем очередной элимент
                    // удаляем сообщение
                    $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                    $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                    if(204 == $code or 404 == $code){// если запрос выполнен успешно
                        $app["fun"]["delСache"]("message", $message["id"], $channel["id"], $guild["id"]);
                        array_splice($items, $i, 1);// удаляем текущее сообщение из списка
                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                };
                // удаляем не группирующиеся сообщения из сформированного списка
                $now = microtime(true);// текущее время
                $time = count($contents) > count($items) ? $now : 0;// время более нового сообщения
                $flag = false;// нужно ли изначально удалить сообщение
                for($i = 0; $i < count($items) and empty($status); $i++){
                    $item = $items[$i];// получаем очередной элимент
                    $flag = ($flag or $time - $item["timestamp"] > $app["val"]["discordMessageTime"]);
                    // удаляем сообщение
                    if($flag){// если сообщения не группируются 
                        $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                        $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                        if(204 == $code or 404 == $code){// если запрос выполнен успешно
                            $app["fun"]["delСache"]("message", $message["id"], $channel["id"], $guild["id"]);
                            array_splice($items, $i, 1);// удаляем текущее сообщение из списка
                            $i--;// уменьшаем индекс
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    }else $time = $item["timestamp"];
                };
            };
            // создаём новые или изменяем имеющиеся сообщения бота
            if(empty($status) and $hasPermission){// если нужно выполнить
                $index = count($items) - 1;// индекс самого первого сообщения бота
                for($i = 0, $iLen = count($contents); $i < $iLen and empty($status); $i++){
                    $content = $contents[$i];// получаем очередной элимент
                    $item = isset($items[$index - $i]) ? $items[$index - $i] : null;
                    if(empty($item)){// если нужно опубликовать новое сообщение
                        // отправляем новое сообщение
                        $uri = "/channels/" . $channel["id"] . "/messages";
                        $data = array("content" => $content);
                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $app["fun"]["setСache"]("message", $data, $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    }else if($item["content"] != $content){// если нужно изменить старое сообщение
                        // изменяем старое сообщение
                        $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                        $data = array("content" => $content);
                        $data = $app["fun"]["apiRequest"]("patch", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $app["fun"]["setСache"]("message", $data, $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    };
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нет ошибок
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        }
    ),
    "format" => array(// поддерживаемые форматы вывода
        "json" => function($data){// в формате json
        //@param $data {array} - массыв выводимых данных
        //@return {boolean} - успешность вывода данных в этом формате
            $error = 0;
            
            // для нормальных браузеров добавляем Content-Type
            if(// множественное условие
                false === strpos($_SERVER["HTTP_USER_AGENT"], "MSIE")
                and false === strpos($_SERVER["HTTP_USER_AGENT"], "Trident")
            ){// если это нормальный браузер
                header("Content-Type: application/json; charset=utf-8");
            };
            echo json_encode($data);
            // возвращаем результат
            return !$error;
        },
        "http" => function($data){// в формате http
        //@param $data {array} - массыв выводимых данных
        //@return {boolean} - успешность вывода данных в этом формате
            global $app;
            $error = 0;
            
            // работа с заголовками
            foreach($data as $key => $value){
                if("response" != $key){// если это не результат работы
                    header("X-Api-".ucfirst(strtolower($key)).": ".$value);
                };
            };
            // работаем с содержимым
            if(is_array($data["response"])) echo json_encode($data["response"]);
            else if(is_bool($data["response"])) echo $data["response"] ? "true" : "false";
            else echo $data["response"];
            // возвращаем результат
            return !$error;
        },
        "xml" => function($data){// в формате xml
        //@param $data {array} - массыв выводимых данных
        //@return {boolean} - успешность вывода данных в этом формате
            global $app;
            $error = 0;
            
            function data2node($node, $data){// добавляет данные к узлу
            //@param $node {DOMNode} - узел к которому нужно добавить данные
            //@param $data {mixed} - данные которые необходимо добавить
            //@return {undefined} - нечего не возвращает
                $document = $node->ownerDocument;
                if(is_array($data)){// для массивов
                    foreach($data as $tag => $value){
                        if(is_numeric($tag)){// если не ассоциативный массив
                            $node->setAttribute("list", "true");
                            $tag = "item";
                        };
                        $item = $document->createElement($tag);
                        $node->appendChild($item);
                        data2node($item, $value);
                    };
                }else{// для не массивов
                    switch(true){// пробигаемся по типам данных
                        case is_null($data):
                            $value = "null";
                            break;
                        case is_bool($data):
                            $value = $data ? "true" : "false";
                            break;
                        case is_integer($data):
                        case is_float($data):
                            $value = empty($data) ? "0" : $data;
                            break;
                        default:
                            $value = $data;
                    };
                    $text = new DOMText($value);
                    $node->appendChild($text);
                };
            };
            // готовим и выводим xml через DOM
            $document = new DOMDocument("1.0", "utf-8");
            $node = $document->createElement("api");
            $document->appendChild($node);
            data2node($node, $data);
            header("Content-Type: text/xml; charset=utf-8");
            echo $document->saveXML();
            // возвращаем результат
            return !$error;
        }
    ),
    "fun" => array(// специфические функции
        "setСache" => function&($type, $data){// добавляем данные в кеш
        //@param $type {string} - тип данных для кеширования
        //@param $data {array} - данные в виде массива 
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - ссылка на элимент данныx или null
            global $app;
            $error = 0;
            
            $argLength = func_num_args();
            $argFirst = 2;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            switch($type){// поддерживаемые типы
                case "guild":// гильдия
                    // проверяем наличее данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            isset($data["id"])
                            and $argLength >= $argFirst
                        ){// если проверка пройдена
                            $gid = $data["id"];
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $gid) break;
                            };
                            if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                            $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // формируем структуру
                    if(!$error){// если нет ошибок
                        foreach(array("members", "channels") as $key){
                            if(isset($data[$key]) and !isset($unit[$key])){
                                $unit[$key] = array();
                            };
                        };
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор
                        $key = "id";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // список участников
                        $key = "members";// задаём ключ
                        if(isset($data[$key])){// если существует
                            for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("member", $data[$key][$i], $gid);
                            };
                        };
                        // список каналов
                        $key = "channels";// задаём ключ
                        if(isset($data[$key])){// если существует
                            for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("channel", $data[$key][$i], $gid, null);
                            };
                        };
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "user":// пользователь
                    // проверяем наличее данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            isset($data["id"])
                            and $argLength >= $argFirst
                        ){// если проверка пройдена
                            $uid = $data["id"];
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $uid) break;
                            };
                            if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                            $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // формируем структуру
                    if(!$error){// если нет ошибок
                        foreach(array("channels") as $key){
                            if(isset($data[$key]) and !isset($unit[$key])){
                                $unit[$key] = array();
                            };
                        };
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор
                        $key = "id";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // признак бота
                        $key = "bot";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        }else if(!isset($unit[$key])){// если не задано
                            $unit[$key] = false;// по умолчанию
                        };
                        // список каналов
                        $key = "channels";// задаём ключ
                        if(isset($data[$key])){// если существует
                            for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("channel", $data[$key][$i], null, $uid);
                            };
                        };
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "member":// участник
                    // проверяем наличее данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            isset($data["user"]["id"])
                            and $argLength > $argFirst
                        ){// если проверка пройдена
                            $uid = $data["user"]["id"];
                            $gid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["user"]["id"] == $uid) break;
                            };
                            if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                            $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // формируем структуру
                    if(!$error){// если нет ошибок
                        foreach(array("user", "roles") as $key){
                            if(isset($data[$key]) and !isset($unit[$key])){
                                $unit[$key] = array();
                            };
                        };
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор пользователя
                        $key = "id";// задаём ключ
                        if(isset($data["user"][$key])){// если существует
                            $unit["user"][$key] = $data["user"][$key];
                        };
                        // признак бота для пользователя
                        $key = "bot";// задаём ключ
                        if(isset($data["user"][$key])){// если существует
                            $unit["user"][$key] = $data["user"][$key];
                        }else if(!isset($unit["user"][$key])){// если не задано
                            $unit["user"][$key] = false;// по умолчанию
                        };
                        // список ролей
                        $key = "roles";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "channel":// канал
                    // проверяем наличее данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            isset($data["id"], $data["type"])
                            and (isset($data["recipients"][0]["id"]) or isset($data["name"]))
                            and $argLength > $argFirst + 1
                        ){// если проверка пройдена
                            $cid = $data["id"];
                            $gid = func_get_arg($argFirst);
                            $uid = func_get_arg($argFirst + 1);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and (!empty($gid) xor !empty($uid))
                            and (0 == $data["type"] or 1 == $data["type"])
                            and (isset($data["recipients"][0]["id"]) ? $uid == $data["recipients"][0]["id"] : 1 != $data["type"])
                            and 1 == count(get_val($data, "recipients", array($uid)))
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        switch(true){// по идентификаторам
                            case !empty($gid): $parent = &$app["fun"]["getСache"]("guild", $gid); break;
                            case !empty($uid): $parent = &$app["fun"]["getСache"]("user", $uid); break;
                            default: $parent = null;
                        };
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $cid) break;
                            };
                            if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                            $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // формируем структуру
                    if(!$error){// если нет ошибок
                        foreach(array("messages") as $key){
                            if(isset($data[$key]) and !isset($unit[$key])){
                                $unit[$key] = array();
                            };
                        };
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор
                        $key = "id";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // название
                        $key = "name";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // тип
                        $key = "type";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // права доступа
                        $key = "permission_overwrites";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // сообщения
                        $key = "messages";// задаём ключ
                        if(isset($data[$key])){// если существует
                            for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("message", $data[$key][$i], $cid, $gid);
                            };
                        };
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "message":// сообщение
                    // проверяем наличее данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            isset($data["id"], $data["type"], $data["timestamp"], $data["pinned"], $data["author"]["id"])
                            and $argLength > $argFirst + 1
                        ){// если проверка пройдена
                            $mid = $data["id"];
                            $cid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                            and !empty($data["timestamp"])
                            and !empty($data["author"]["id"])
                            and (!$data["pinned"] or $data["author"]["id"] == $app["val"]["discordBotId"])
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элимент
                            $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $cid, $gid);
                            $flag = ($permission & $app["val"]["discordBotPermission"]) == $app["val"]["discordBotPermission"];
                            if($flag or isset($parent[$key])){// если есть разрешения или учёт уже ведётся
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $mid) break;
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        }else $error = 4;
                    };
                    // формируем структуру
                    if(!$error){// если нет ошибок
                        foreach(array("author") as $key){
                            if(isset($data[$key]) and !isset($unit[$key])){
                                $unit[$key] = array();
                            };
                        };
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор
                        $key = "id";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // идентификатор автора
                        $key = "id";// задаём ключ
                        if(isset($data["author"][$key])){// если существует
                            $unit["author"][$key] = $data["author"][$key];
                        };
                        // признак бота для автора
                        $key = "bot";// задаём ключ
                        if(isset($data["author"][$key])){// если существует
                            $unit["author"][$key] = $data["author"][$key];
                        }else if(!isset($unit["author"][$key])){// если не задано
                            $unit["author"][$key] = false;// по умолчанию
                        };
                        // тип
                        $key = "type";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // время создания
                        $key = "timestamp";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $value = $data[$key];// получаем значение
                            if(is_string($value)){// если нужно преобразовать в число
                                if(32 == mb_strlen($value)){// если указаны миллисекунды
                                    $unit[$key] = strtotime(mb_substr($value, 0, 19) . mb_substr($value, 26));
                                    $unit[$key] += (float) "0" . mb_substr($value, 19, 7);
                                }else $unit[$key] = strtotime($value);
                            }else $unit[$key] = $value;
                        };
                        // содержимое
                        $key = "content";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                        // закрепление
                        $key = "pinned";// задаём ключ
                        if(isset($data[$key])){// если существует
                            $unit[$key] = $data[$key];
                        };
                    };
                    // сортируем элименты
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        usort($parent[$key], function($a, $b){// сортировка
                            $value = 0;// начальное значение
                            if(!$value and $a["timestamp"] != $b["timestamp"]) $value = $a["timestamp"] < $b["timestamp"] ? 1 : -1;
                            if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                            // возвращаем результат
                            return $value;
                        });
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                default:// не известный тип
                    $error = 1;
            };
            // возвращаем результат
            return $cache;
        },
        "getСache" => function&($type){// получает данные из кеша
        //@param $type {string} - тип данных для кеширования
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - ссылка на элимент данныx или null
            global $app;
            $error = 0;
            
            $argLength = func_num_args();
            $argFirst = 1;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            switch($type){// поддерживаемые типы
                case "guild":// гильдия
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $gid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $gid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/guilds/" . $gid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data);
                        }else $error = 5;
                    };
                    // получаем каналы через api
                    $key = "channels";// задаём ключ
                    if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                        $uri = "/guilds/" . $gid . "/" . $key;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            if(!isset($unit[$key])) $unit[$key] = array();
                            for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("channel", $data[$i], $gid, null);
                            };
                        }else $error = 7;
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "user":// пользователь
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $uid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $uid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/users/" . $uid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data);
                        }else $error = 5;
                    };
                    // получаем каналы через api
                    $key = "channels";// задаём ключ
                    if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                        $uri = "/users/" . $app["val"]["discordBotId"] . "/" . $key;
                        $data = array("recipient_id" => $uid);
                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            if(!isset($unit[$key])) $unit[$key] = array();
                            $app["fun"]["setСache"]("channel", $data, null, $uid);
                        }else $error = 7;
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "member":// участник
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $uid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["user"]["id"] == $uid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/guilds/" . $gid . "/members/" . $uid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data, $gid);
                        }else $error = 5;
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "channel":// канал
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 2
                        ){// если проверка пройдена
                            $cid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                            $uid = func_get_arg($argFirst + 2);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and (!empty($gid) xor !empty($uid))
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        switch(true){// по идентификаторам
                            case !empty($gid): $parent = &$app["fun"]["getСache"]("guild", $gid); break;
                            case !empty($uid): $parent = &$app["fun"]["getСache"]("user", $uid); break;
                            default: $parent = null;
                        };
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $cid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/channels/" . $cid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data, $gid, $uid);
                        }else $error = 5;
                    };
                    // получаем сообщения через api
                    $key = "messages";// задаём ключ
                    if(!$error and $unit and !empty($gid) and !isset($unit[$key])){// если нужно выполнить
                        $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $unit, $gid);
                        $flag = ($permission & $app["val"]["discordBotPermission"]) == $app["val"]["discordBotPermission"];
                        if($flag){// если проверка пройдена
                            $uri = "/channels/" . $cid  . "/" . $key;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                if(!isset($unit[$key])) $unit[$key] = array();
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setСache"]("message", $data[$i], $cid, $gid);
                                };
                            }else $error = 6;
                        };
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "message":// сообщение
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 1
                        ){// если проверка пройдена
                            $mid = func_get_arg($argFirst);
                            $cid = func_get_arg($argFirst + 1);
                            $gid = func_get_arg($argFirst + 2);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $mid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/channels/" . $cid . "/messages/" . $mid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data, $cid, $gid);
                        }else $error = 5;
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                default:// не известный тип
                    $error = 1;
            };
            // возвращаем результат
            return $cache;
        },
        "delСache" => function($type){// удаляет данные из кеша
        //@param $type {string} - тип данных для кеширования
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - элимент данныx или null
            global $app;
            $error = 0;
            
            $argLength = func_num_args();
            $argFirst = 1;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            switch($type){// поддерживаемые типы
                case "guild":// гильдия
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $gid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $gid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "user":// пользователь
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $uid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $uid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "member":// участник
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $uid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["user"]["id"] == $uid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "channel":// канал
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 1
                        ){// если проверка пройдена
                            $cid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                            $uid = func_get_arg($argFirst + 2);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and (!empty($gid) xor!empty($uid))
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        switch(true){// по идентификаторам
                            case !empty($gid): $parent = &$app["fun"]["getСache"]("guild", $gid); break;
                            case !empty($uid): $parent = &$app["fun"]["getСache"]("user", $uid); break;
                            default: $parent = null;
                        };
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $cid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "message":// сообщение
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 1
                        ){// если проверка пройдена
                            $mid = func_get_arg($argFirst);
                            $cid = func_get_arg($argFirst + 1);
                            $gid = func_get_arg($argFirst + 2);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $mid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                default:// не известный тип
                    $error = 1;
            };
            // возвращаем результат
            return $cache;
        },
        "changeEvents" => function($time, &$status){// делаем регламентные операции с событиями
        //@param $time {float} - данные о времени для выполнения регламентных операций
        //@param $status {number} - целое число статуса выполнения
        //@return {array} - список изменённых событий по идентификаторам
            global $app; $list = array();
        
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // корректируем события по времени
            if(empty($status)){// если нет ошибок
                for($i = $events->length - 1; $i >= 0 and empty($status); $i--){
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($id);// получаем элимент по идентификатору
                    $value = $event["time"];// время события
                    // корректируем повторяющиеся события
                    if($event["repeat"]){// если это повторяющиеся событие
                        // корректируем устаревшие повторяющиеся события
                        while($value < $time - $app["val"]["eventTimeDelete"]){
                            $value = $value + $event["repeat"];
                        };
                        // корректируем слишком далёкие повторяющиеся события
                        while($value > $time + $app["val"]["eventTimeAdd"]){
                            $value = $value - $event["repeat"];
                        };
                    };
                    // определяем события выходящие за ограничения по времени
                    if(// множественное условие
                        $value < $time - $app["val"]["eventTimeDelete"]
                        or $value > $time + $app["val"]["eventTimeAdd"]
                    ){// если нужно удалить событие
                        $value = 0;
                    };
                    // вносим изменения в базу данных
                    if($event["time"] != $value){// если есть изменения
                        $flag = $value ? $events->set($id, "time", $value) : $events->set($id);
                        if($flag){// если данные успешно изменены
                            $list[$id] = $event;// добавляем событие в список изменённых
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // считаем события для проверки лимитов
            if(empty($status)){// если нет ошибок
                $counts = array();// счётчики записи
                $all = "";// идентификатор всех рейдов
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($id);// получаем элимент по идентификатору
                    // создаём структуру счётчика
                    $count = &$counts;// счётчик элиментов
                    foreach(array($event["guild"], $event["channel"], $event["user"], $event["time"]) as $key){
                        if(!isset($count[$key])) $count[$key] = array();
                        $count = &$count[$key];// получаем ссылку
                    };
                    // выполняем подсчёт событий
                    if(!isset($count[$event["raid"]])) $count[$event["raid"]] = 0;
                    if(!isset($count[$all])) $count[$all] = 0;
                    $count[$event["raid"]]++;
                    $count[$all]++;
                };
            };
            // удаляем события по лимитам
            if(empty($status)){// если нет ошибок
                foreach(array(false, true) as $repeat){// пробегаемся по значениям повторяемости
                    for($i = $events->length - 1; $i >= 0 and empty($status); $i--){
                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                        $event = $events->get($id);// получаем элимент по идентификатору
                        if($repeat == !!$event["repeat"]){// если событие соответствует
                            // получаем счётчик из структуру
                            $count = &$counts;// счётчик элиментов
                            foreach(array($event["guild"], $event["channel"], $event["user"], $event["time"]) as $key){
                                $count = &$count[$key];// получаем ссылку
                            };
                            // работаем с лимитами
                            if(// множественное условие
                                $count[$event["raid"]] > $app["val"]["eventRaidLimit"]
                                or $count[$all] > $app["val"]["eventTimeLimit"]
                            ){// если есть привышение лимитов
                                if($events->set($id)){// если данные успешно изменены
                                    $list[$id] = $event;// добавляем событие в список изменённых
                                    $count[$event["raid"]]--;
                                    $count[$all]--;
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                        };
                    };
                };
            };
            // возвращаем результат
            return $list;
        },
        "getNextValue" => function(&$content, $delim){// получаем очередное значение
        //@param $content {string} - страка значений для обработки (сокращается на значение и разделители)
        //@param $delim {string} - разделитель значенией в строке
        //@return {string} - очередное значение
            
            // получаем значение из строки по разделителю
            $index = mb_stripos($content, $delim);
            if(false === $index) $value = $content;
            else $value = mb_substr($content, 0, $index);
            // сокращаем строку на значение и разделители
            $content = mb_substr($content, mb_strlen($value));
            while(0 === mb_stripos($content, $delim)){// пока есть разделитель
                $content = mb_substr($content, mb_strlen($delim));
            };
            // возвращаем результат
            return $value;
        },
        "getItemKey" => function($items, &$content, $begin, $cut){// получаем ключевой идентификатор
        //@param $items {FileStorage} - база данных для поиска совподений значений
        //@param $content {string} - страка для обработки (может сокращается)
        //@param $begin {boolean} - совпадение с началом свойства элимента
        //@param $cut {boolean} - сократить исходную строку на совподение
        //@return {string} - ключ или изначальное значение
            
            $key = "";// значение по умолчанию
            $flag = false;// найдено совпадение
            // выполняем последовательную проверку элиментов
            if(mb_strlen($content)){// если передана не пустая строка
                for($i = 0, $iLen = $items->length; $i < $iLen and !$flag; $i++){
                    $id = $items->key($i);// получаем ключевой идентификатор по индексу
                    $item = $items->get($id);// получаем элимент по идентификатору
                    // выполняем последовательную проверку свойств элиментов
                    foreach(array($items->primary, "synonym") as $property){// пробигаемся по свойствам
                        if(isset($item[$property])){// если свойство существует
                            $value = $item[$property];// получаем значение свойства
                            $length = mb_strlen($value);// получаем длину значения
                            $index = mb_stripos($content, $value);// позиция начала совпадения
                            $flag = (0 === $index and ($begin or mb_strlen($content) == $length));
                            if($flag){// если найдено совпадение со свойством элимента
                                if($cut) $content = mb_substr($content, $length);
                                $key = $id;
                                break;
                            };
                        };
                    };
                };
            };
            //обрабатываем отсутствие совпадений
            if($cut and !$flag){// если нужно обрезать
                $key = $content;
                $content = "";
            };
            // возвращаем результат
            return $key;
        },
        "getPermission" => function($level, $member, $channel, $guild){// получаем разрешения
        //@param $level {string} - уровень проверки разрешений
        //@param $member {array|string} - участник или его идентификатор
        //@param $channel {array|string} - канал или его идентификатор
        //@param $guild {array|string} - гильдия или её идентификатор
        //@return {integer} - значение разрешений
            global $app;
            $error = 0;
            
            $permission = 0;// разрешения
            // получаем гильдию
            if(!$error){// если нет ошибок
                if(!is_array($guild) and !empty($guild)){// если нужно получить
                    $guild = $app["fun"]["getСache"]("guild", $guild);
                };
                if(!empty($guild)){// если не пустое значение
                }else $error = 1;
            };
            // получаем канал
            if(!$error){// если нет ошибок
                if(!is_array($channel) and !empty($channel)){// если нужно получить
                    $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"], null);
                };
                if(!empty($channel)){// если не пустое значение
                }else $error = 2;
            };
            // получаем участника
            if(!$error){// если нет ошибок
                if(!is_array($member) and !empty($member)){// если нужно получить
                    $member = $app["fun"]["getСache"]("member", $member, $guild["id"]);
                };
                if(!empty($member)){// если не пустое значение
                }else $error = 3;
            };
            // последовательно вычисляем разрешения
            if(!$error and isset($channel["permission_overwrites"])){// если нужно выполнить
                switch($level){// поддерживаемые уровни проверки разрешений
                    case "channel":// начиная с канала
                        // проверяем перезапись разрешений для всех
                        for($i = 0, $iLen = count($channel["permission_overwrites"]); $i < $iLen; $i++){
                            $overwrite = $channel["permission_overwrites"][$i];// получаем очередную перезапись
                            if($guild["id"] == $overwrite["id"] and "role" == $overwrite["type"]){// если для всех
                                $permission &= ~$overwrite["deny"];
                                $permission |= $overwrite["allow"];
                                break;
                            };
                        };
                    case "role":// начиная с ролей
                        // проверяем перезапись разрешений для ролей участника
                        $permissions = array("allow" => 0, "deny" => 0);
                        for($i = 0, $iLen = count($channel["permission_overwrites"]); $i < $iLen; $i++){
                            $overwrite = $channel["permission_overwrites"][$i];// получаем очередную перезапись
                            for($j = 0, $jLen = count($member["roles"]); $j < $jLen; $j++){
                                $rid = $member["roles"][$j];// получаем очередной идентификатор роли
                                if($rid == $overwrite["id"] and "role" == $overwrite["type"]){// если для этой роли
                                    $permissions["allow"] |= $overwrite["allow"];
                                    $permissions["deny"] |= $overwrite["deny"];
                                    break;
                                };
                            };
                        };
                        $permission &= ~$permissions["deny"];
                        $permission |= $permissions["allow"];
                    case "member":// начиная с участника
                        // проверяем перезапись разрешений для участника
                        for($i = 0, $iLen = count($channel["permission_overwrites"]); $i < $iLen; $i++){
                            $overwrite = $channel["permission_overwrites"][$i];// получаем очередную перезапись
                            if($member["user"]["id"] == $overwrite["id"] and "member" == $overwrite["type"]){// если для участника
                                $permission &= ~$overwrite["deny"];
                                $permission |= $overwrite["allow"];
                                break;
                            };
                        };
                };
            };
            // возвращаем результат
            return $permission;
        },
        "apiRequest" => function($metod, $uri, $data = null, &$code = 0){// http запрос к api
        //@param $metod {string} - методов http запроса в нижнем регистре
        //@param $uri {string} - конечная часть url адреса запроса
        //@param $data {array} - строка массив данных для запроса
        //@param $code {integer} - код ответа сервера
        //@return {array|null} - полученные данные или null при ошибки
            global $app;
            static $time = 0;
            $response = null;
            
            $wait = 0;// время ожидания сброса лимита
            $remain = 1;// текущий остаток запросов        
            // делаем запрос через api
            if(!empty($metod) and !empty($uri)){// если не пустые значения
                // контролируем скорость запросов
                $now = microtime(true);// текущее время
                $value = $time - $now;// время ожидания
                if($value > 0) usleep(1000000 * $value);
                // готовим данные и выполняем запрос
                $headers = array();// стандартные заголовки для запроса
                $headers["authorization"] = "Bot " . $app["val"]["discordBotToken"];
                $headers["x-ratelimit-precision"] = "millisecond";
                if(!empty($data)) $headers["content-type"] = "application/json;charset=utf-8";
                if(!empty($data)) $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                $data = http($metod, $app["val"]["discordApiUrl"] . $uri, $data, null, $headers, false);
                // обрабатываем ограничения скорости запросов
                foreach($data["headers"] as $key => $value) if("x-ratelimit-remaining" == mb_strtolower($key)) $remain = (int)$value;
                foreach($data["headers"] as $key => $value) if("x-ratelimit-reset-after" == mb_strtolower($key)) $wait = (float)$value;
                if(empty($remain) and !empty($wait)) $time = max($time, microtime(true) + $wait);
                // обрабатываем полученный ответ
                $code = $data["status"];// устанавливаем код ответа сервера
                $data = json_decode($data["body"], true);// преобразовываем данные
                if(!empty($data) or is_array($data)) $response = $data;
            };
            // возвращаем результат
            return $response;
        },
        "getStorage" => function($name, $lock = false){// получает базу данных
        //@param $name {string} - имя запрашиваемой базы данных
        //@param $lock {boolean} - установить блокировку при первом подключении
        //@return {null|FileStorage} - ссылка на базу данных
            global $app;
            
            if(!empty($name)){// если передано название
                if(!isset($app["base"][$name])){// если база данных еще не загружалась
                    $path = template($app["val"]["baseUrl"], array("name" => $name));
                    // поправка на проверку монопольного доступа к файлу базы
                    $flag = false;// пройдена ли проверка на монопольный доступ
                    for($i = 0, $iLen = 55; $i < $iLen and $lock and !$flag; $i++){
                        if($i) sleep(1);// ждём секунду между итерациями
                        if(file_exists($path)){// если файл существует
                            $source = @fopen($path, "r");// открываем на чтение
                            if($source){// если удалось открыть
                                $flag = @flock($source, LOCK_EX | LOCK_NB);
                                if($flag) @flock($source, LOCK_UN);
                                @fclose($source);
                            };
                        };
                    };
                    // работаем с объектом базы данных
                    $storage = new FileStorage($path);// создаём объект
                    if(!$lock or $flag and $storage->lock(true)){// если пройдена проверка
                        if($storage->load($lock)){// если удалось открыть базу данных
                            $app["base"][$name] = &$storage;
                        }else $storage = null;
                    }else $storage = null;
                }else $storage = &$app["base"][$name];
            }else $storage = null;
            // возвращаем результат
            return $storage;
        },
        "setStatus" => function($id){// устанавливает статус по его идентификатору
        //@param $id {number} - идентификатор устанавливаемого статусного сообщения
        //@return {boolean} - успешность установки указанного статуса
            global $app, $result;
            $error = 0;
            
            $statuses = $app["fun"]["getStorage"]("statuses");
            if(!empty($statuses)){// если база данных статусов существует
                if(!empty($id)){// если передан не пустой идентификатор
                    $result["msg"] = $statuses->get($id, $app["val"]["statusLang"]);
                }else $error = 2;
            }else $error = 1;
            if(!$result["msg"]) $result["msg"] = $app["val"]["statusUnknown"];
            $result["status"] = $id;
            // возвращаем результат
            return !$error;
        },
        "getClearParam" => function(&$params, $name, $filter = "string", $list = 0){// фильтреет параметр массива по заданному фильтру
        //@param $params {array} - ассоциативный массив параметров
        //@param $name {string|integer|float} - идентификатор параметра для фильтрации
        //@param $list {integer|true} - сколько УНИКАЛЬНЫХ значений получать из содержимаго (если не равно нулю, то возвращается массив, если true то весь массив)
        //@return {null|false|array|mixed} - null при отсутствии параметра, false при не соответствии фильтру или отфильтрованное значение (или массив отфильтрованных значений)
            $values = $value = null; $delim = ","; $flags = array();
            
            if(isset($params[$name])){// если параметр с таким именем существует
                // готовим список на фильтрацию
                $values = array();
                $value = $params[$name];
                if($list){// если запрашивается список
                    $array = explode($delim, $value);
                    for($i = 0, $iLen = count($array); $i < $iLen; $i++){
                        if(true !== $list and count($values) >= $list) break 1;
                        $value = $array[$i];
                        if(!isset($flags[$value])){// если это уникальное значение
                            $flags[$value] = 1;
                            $values[] = $value;
                        }else $flags[$value]++;
                    };
                }else $values[] = $value;
                // фильтруем каждый элимент списка
                for($i = 0, $iLen = count($values); $i < $iLen; $i++){
                    $value = is_string($values[$i]) ? trim($values[$i]) : $values[$i];
                    switch($filter){// фильтры основанные на регулярных вырожениях
                        case "password": $filter = "(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$"; break; // строчные и прописные латинские буквы, цифры, спецсимволы, минимум 8 символов
                        case "md5": $filter = "^[0-9a-f]{32}$"; $value = mb_strtolower($value); break;
                        case "sha1": $filter = "^[0-9a-f]{40}$"; $value = mb_strtolower($value); break;
                        case "number": $filter = "^\d+$"; break;
                    };
                    switch($filter){// пробигаемся по поддердиваемым фильтрам
                        case "integer": $value = filter_var($value, FILTER_VALIDATE_INT); if(false === $value) (int)$value; break;
                        case "natural": $value = filter_var($value, FILTER_VALIDATE_INT, array("options" => array("min_range" => 0))); if(false === $value) (int)$value; break;
                        case "float": $value = filter_var($value, FILTER_VALIDATE_FLOAT); if(false === $value) (float)$value; break;
                        case "boolean": $value = filter_var($value, FILTER_VALIDATE_BOOLEAN); if(false === $value) $value = 0; break;
                        case "email": $value = filter_var($value, FILTER_VALIDATE_EMAIL); break;
                        case "string": $value = filter_var($value, FILTER_SANITIZE_STRING); if(empty($value)) $value = false; break;
                        case "chars": $value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS); if(empty($value)) $value = false; break;
                        // специализированные фильтры без регулярных вырожений
                        case "file": if(!(isset($value["name"], $value["type"], $value["tmp_name"], $value["error"], $value["size"]) and UPLOAD_ERR_OK == $value["error"] and $value["size"] > 0)) $value = false; break;
                        // фильтры основанные на регулярных вырожениях
                        default: $value = filter_var($value, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/".$filter."/")));
                    };
                    $values[$i] = $value;
                };
            };
            // возвращаем результат
            return $list ? $values : $value;
        }
    )
);

// выставляем время по часам сервера
date_default_timezone_set("Europe/Moscow");
// настраиваем крон на полное выполнение скрипта
ini_set("max_input_time", 0);
ini_set("ignore_user_abort", 1);
ini_set("max_execution_time", 0);
// готовим список полученных параметров
$params = array();
foreach($_GET as $key => $value){
    if(!preg_match("//u", $value)){
        $value = iconv("cp1251", "UTF-8", $value);
    };
    $key = str_replace("_", ".", $key);
    $params[$key] = $value;
};
foreach($_POST as $key => $value){
    $key = str_replace("_", ".", $key);
    if(!isset($params[$key])){
        $params[$key] = $value;
    };
};
ksort($params);
// обработываем полученные данные
$result = array("response" => null, "status" => 0, "msg" => "");
$statuses = $app["fun"]["getStorage"]("statuses", false);
$status = $result["status"];
if(!empty($statuses)){// если удалось получить доступ к базе данных
    $method = $app["fun"]["getClearParam"]($params, "method", "string");
    if(!is_null($method)){// если задан метод в запросе
        if(!empty($method)){// если фильтрация метода прошла успешно
            if(isset($app["method"][$method])){// если метод есть в списке поддерживаемых методов
                $result["response"] = $app["method"][$method]($params, array(), false, $status);
            }else $status = 308;// запрашиваемый метод не поддерживается
        }else $status = 302;// один из обязательных параметров передан в неверном формате
    }else $status = 301;// не передан один из обязательных параметров
}else $status =304;// не удалось загрузить одну из многих баз данных
if(empty($status)) $status = 200;// успешно выполено
$app["fun"]["setStatus"]($status);
// выводим результат работы скрипта
header("Server: Simple API 0.1.0");
$format = $app["fun"]["getClearParam"]($params, "format", "string");
if(empty($format) or !isset($app["format"][$format])){// если задан не поддерживаемый формат
    $format = $app["val"]["format"];// устанавливаем формат поумолчанию
};
header("Cache-Control: no-store");
header("Pragma: no-cache");
$app["format"][$format]($result);
?>