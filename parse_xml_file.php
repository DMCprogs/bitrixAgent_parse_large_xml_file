<?
// размер шага
const STEP = 30; 
public	static function XmlParser($itemParsed)
{    
    
Loader::includeModule('iblock');
Loader::includeModule('catalog');
Loader::includeModule('sale'); 
$xmlFile = "ссылка на ваш xml файл";


    $xml = simplexml_load_file($xmlFile);
    if ($xml === false) {
        echo "Ошибка при загрузке XML файла.";
        exit;
    }

    // ID инфоблока
    $iblockId = "id инфоблока";
    $secitonId="id секции";
    $start = $itemParsed;
    $offers = $xml->shop->offers->offer;
    $total = iterator_count($offers);
    $step="размер шага";
    for ($i = $start; $i < $total && $counter <  $step; $i++) {
        $item = $offers[$i];
        $modifiedString = str_replace(' ', '_', (string)$item->name);
        $paramsArray = [];
        foreach ($item->param as $param) {
            $name = (string)$param['name'];
            $value = (string)$param;
            $paramsArray[$name] = $value;
        }
        foreach ($item->city as $city) {
           
            $cityArray[] = (string)$city;
            
        }
        $pictureArray=array();
        foreach ($item->picture as $picture) {
            $pictureUrl = (string)$picture;
            $picturePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/tmp/' . basename($pictureUrl);
            
            // Скачиваем картинку и сохраняем её на сервере
            if (copy($pictureUrl, $picturePath)) {
           
                $pictureArray[] =  \CFile::MakeFileArray($picturePath);
            }
        }
        $arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM");
        $arFilter = Array("IBLOCK_ID"=>4,"XML_ID"=>$item["id"], "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");
        $res = \CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
        if($ob = $res->GetNextElement())
        {
            $arFields = $ob->GetFields();
            
        }
     
        $fields = [
            'NAME' => (string)$item->name,
            'CODE' =>  $modifiedString,
            'ACTIVE' => 'Y',
            'IBLOCK_ID' => $iblockId,
            'IBLOCK_SECTION_ID'=>$secitonId,
            'XML_ID'=>$item["id"],
            "DETAIL_PICTURE" => $pictureArray[0] ,
            "PREVIEW_PICTURE" =>   $pictureArray[0],
            'PROPERTY_VALUES' => [
                'SKILL_CITY' =>  $cityArray,
                'DIAMETR' =>  $paramsArray["Диаметр"],
                'DRILLING'=>$paramsArray["Сверловка"],
                'WIDTH'=>$paramsArray["Ширина"],
                'WIDTH2'=>$paramsArray["Ширина#2"],
                'DEPARTMENT'=>$paramsArray["Вылет"],
                'DIA'=>$paramsArray["DIA"],
                'TYPE_DISK'=>$paramsArray["Тип диска"],
                'SHORT_NAME'=>(string)$item->name,
               
            ],
        ];
        array_shift($pictureArray);
        $fields['PROPERTY_VALUES']['MORE_PHOTO'] = $pictureArray;
        if ($paramsArray["Разноширокие"] == "Да") {
            $fields['PROPERTY_VALUES']['UNEVEN'] = array("VALUE" =>56836);
        }

        if(!empty($arFields["ID"])){
            $element = new \CIBlockElement;  
            $PROPERTY_VALUE  =  array (
                0  =>  array ( "VALUE" => "" , "DESCRIPTION" => "" )
              ); 
            \CIBlockElement::SetPropertyValuesEx($arFields["ID"], 4, array('SKILL_CITY' => $PROPERTY_VALUE));
            \CIBlockElement::SetPropertyValuesEx($arFields["ID"], 4, array('MORE_PHOTO' => $PROPERTY_VALUE));
            $elementId = $element->Update($arFields["ID"],$fields);
            $productID = \CCatalogProduct::add(array("ID" =>  $elementId, "QUANTITY" => 1));
        }
        else {
            $element = new \CIBlockElement;
            $elementId = $element->Add($fields);
            $productID = \CCatalogProduct::add(array("ID" =>  $elementId, "QUANTITY" => 1));
        }

       
        if (!empty($item->price)) {
            $priceValue = (int)$item->price;
    
            $priceData = [
                'PRODUCT_ID' => $elementId,
                'CATALOG_GROUP_ID' => 2,
                'PRICE' => $priceValue,
                'CURRENCY' => 'RUB',
            ];
    
            $existingPrice = \Bitrix\Catalog\Model\Price::getList([
                'filter' => [
                    'PRODUCT_ID' => $elementId,
                    'CATALOG_GROUP_ID' =>2,
                ],
            ])->fetch();
    
            if ($existingPrice) {
                \Bitrix\Catalog\Model\Price::update($existingPrice['ID'], $priceData);
            } else {
                \Bitrix\Catalog\Model\Price::add($priceData);
            }
            $arFieldsStoreAmount = Array(
                "PRODUCT_ID" => $elementId,
                "STORE_ID" => "id вашего склада",
                "AMOUNT" => 1
            );
            $amount = \CCatalogStoreProduct::Add($arFieldsStoreAmount);
            
        }
        
       
        $counter++;
        
    }
    return($counter);
   
}
public static function checkElemnts()
{
\Bitrix\Main\Loader::includeModule('iblock');

$xmlFile = "ссылка на ваш xml файл";
$xml = simplexml_load_file($xmlFile);
if ($xml === false) {
    echo "Ошибка при загрузке XML файла.";
    exit;
}

// Извлекаем все внешние коды из XML файла
$xmlIds = [];
foreach ($xml->shop->offers->offer as $item) {
    $xmlIds[] = (string)$item["id"];
}

// ID инфоблока и раздела
$iblockId = "";
$sectionId = "";

// Получаем все элементы из инфоблока и раздела
$arSelect = ["ID", "XML_ID"];
$arFilter = [
    "IBLOCK_ID" => $iblockId,
    "SECTION_ID" => $sectionId,
    "INCLUDE_SUBSECTIONS" => "N"
];
$res = \CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

while($element = $res->GetNext()) {
    if (!in_array($element["XML_ID"], $xmlIds)) {
        // Дезактивируем элемент, если его XML_ID нет в массиве XML_ID из XML файла
        $ElementArray = Array("ACTIVE" => "N",);

        $element->Update($element['ID'], $ElementArray);
    }
}
}
public static function DeleteOldAgent($itemParsed,$agentID)
{
    if ($arAgent = \CAgent::GetById($agentID)->Fetch()) {
        $currentInterval = $arAgent['AGENT_INTERVAL'];
    } elseif ($arAgent = \CAgent::GetList([], ['NAME' => 'Core::DeleteOldAgent%'])->Fetch()) {
        $currentInterval = $arAgent['AGENT_INTERVAL'];
        $agentID = $arAgent['ID'];
    } else {
        return false;
    }

    if (!isset($GLOBALS['USER']) || !is_object($GLOBALS['USER'])) {
        $bTmpUser = true;
        $GLOBALS['USER'] = new \CUser;
    }

    $nRowsDeleted = self::XmlParser($itemParsed);
    $itemsParsered=$nRowsDeleted+$itemParsed;
    if ($nRowsDeleted == self::STEP) {
        $currentInterval = 60;
        \CAgent::Update($agentID, ['AGENT_INTERVAL' => $currentInterval]);
        if ($bTmpUser) {
            unset($GLOBALS['USER']);
        }
        return "\Sok\Forward\Handlers::DeleteOldAgent($itemsParsered,$agentID);";
    } elseif ($nRowsDeleted  < self::STEP) {
        self::checkElemnts();
        $currentInterval ="выствавите интервал который вам нужен после завершения парсинга";
        \CAgent::Update($agentID, ['AGENT_INTERVAL' => $currentInterval]);
        if ($bTmpUser) {
            unset($GLOBALS['USER']);
        }
        return "\Sok\Forward\Handlers::DeleteOldAgent(0,$agentID);";
    }

    

  
}
// добавляет свойство корзины если есть товар в корзине который запарсили
function OnBasketAddHandlerSkill($ID, $arFields) {
    if (!\Loader::IncludeModule("iblock")) {
        return;
    }
    
    $productId = $arFields['PRODUCT_ID'];
    $propertyCity = [];
    $res = CIBlockElement::GetList(
        [],
        ['ID' => $productId],
        false,
        false,
        ['ID', 'IBLOCK_ID', 'PROPERTY_SKILL_CITY']
    );

    if ($element = $res->Fetch()) {
        $propertyCity = $element['PROPERTY_SKILL_CITY_VALUE'];
    }
    
    if (is_array($propertyCity) && !empty($propertyCity)) {
        CSaleBasket::Update(
            $ID, 
            [
                "PROPS" => [
                    [
                        "NAME" => "Наличие в городах",
                        "CODE" => "SKILL_CITY",
                        "VALUE" => implode(', ', $propertyCity)  // Если это массив строк, объединяем через запятую
                    ]
                ]
            ]
        );
    }
}