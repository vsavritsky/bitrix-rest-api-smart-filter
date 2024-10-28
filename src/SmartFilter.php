<?php

namespace BitrixRestApiSmartFilter;

use Bitrix\Iblock\PropertyIndex\Facet;
use Bitrix\Iblock\PropertyIndex\Storage;
use BitrixModels\Model\Filter;
use BitrixRestApiSmartFilter\Config\ConfigFilter;
use BitrixRestApiSmartFilter\Config\ConfigFilterList;
use BitrixRestApiSmartFilter\Config\ConfigFilterRange;
use CCatalogGroup;
use CFile;
use CIBlockElement;
use CIBlockPriceTools;
use CIBlockProperty;
use CIBlockPropertyEnum;
use CIBlockSectionPropertyLink;
use CCatalogSKU;

class SmartFilter
{
    /** @var \Bitrix\Iblock\PropertyIndex\Facet|null */
    public $facet = null;

    protected $iblockId;
    protected $skuIblockId;
    protected $skuPropertyId;

    protected $sectionId;

    protected $SAFE_FILTER_NAME = 'filter';

    public function __construct($iblockId)
    {
        $this->iblockId = $iblockId;
        if (class_exists('CCatalogSKU')) {
            $arCatalog = CCatalogSKU::GetInfoByProductIBlock($this->iblockId);
            if (!empty($arCatalog)) {
                $this->skuIblockId = $arCatalog["IBLOCK_ID"];
                $this->skuPropertyId = $arCatalog["SKU_PROPERTY_ID"];
            }
        }
        $this->facet = new Facet($this->iblockId);
    }

    public function convertFilter($sectionId, $filterData, Filter $filter = null): Filter
    {
        if (!$filter) {
            $filter = Filter::create();
        }

        $configFilter = $this->getConfigFilter($sectionId, $filter);
        $userFilter = Filter::create();

        foreach ($filterData as $property => $value) {
            $fieldConfig = $configFilter->getConfigFilterItemByCode(str_replace('PROPERTY_', '', $property));

            if (is_array($value)) {
                $values = [];
                foreach ($value as $key => $valueItem) {
                    $values[] = $valueItem;
                }

                if (strpos($property, 'CATALOG_PRICE') !== false) {
                    $userFilter->in($property, $values);
                } else {
                    if (in_array($fieldConfig->getPropertyType(), ['L', 'E'])) {
                        $newValues = [];
                        foreach ($fieldConfig->getValues() as $fieldValue) {
                            if ($fieldValue['urlId'] === $value || $fieldValue['value'] === $value) {
                                $newValues[] = $fieldValue['facetValue'];
                            }
                        }
                        $userFilter->in('PROPERTY_' . $property, $newValues);
                    } else {
                        $userFilter->in('PROPERTY_' . $property, $values);
                    }

                }
            } else {
                if (in_array($fieldConfig->getPropertyType(), ['L', 'E'])) {
                    foreach ($fieldConfig->getValues() as $fieldValue) {
                        if ($fieldValue['urlId'] === $value || $fieldValue['value'] === $value) {
                            $userFilter->eq('PROPERTY_' . $property, $fieldValue['facetValue']);
                        }
                    }
                } else {
                    $userFilter->eq('PROPERTY_' . $property, $value);
                }
            }
        }

        $skuFilter = (new Filter())->eq('IBLOCK_ID', $this->skuIblockId);
        $filterResultList = $userFilter->getResult();

        foreach ($filterResultList as $property => $userFilterValue) {
            $fieldConfig = $configFilter->getConfigFilterItemByCode(str_replace('PROPERTY_', '', $property));
            if ($fieldConfig->getIblockId() == $this->skuIblockId) {
                if ($fieldConfig && $fieldConfig->getDisplayType() === 'R') {
                    $skuFilter->between($property, $userFilterValue[0], $userFilterValue[1]);
                } elseif (is_array($userFilterValue) && count($userFilterValue) > 0) {
                    $skuFilter->in($property, $userFilterValue);
                } else if ($userFilterValue) {
                    $skuFilter->eq($property, $userFilterValue);
                }
            }
        }

        if (count($skuFilter->getResult()) > 1) {
            $filter->eq('ID', CIBlockElement::SubQuery('PROPERTY_CML2_LINK', $skuFilter->getResult()));
        }

        foreach ($filterResultList as $property => $userFilterValue) {
            $fieldConfig = $configFilter->getConfigFilterItemByCode(str_replace('PROPERTY_', '', $property));

            if ($fieldConfig->getIblockId() == $this->iblockId || !$fieldConfig->getIblockId()) {
                if ($fieldConfig && $fieldConfig->getDisplayType() == 'R') {
                    $filter->between($property, $userFilterValue[0], $userFilterValue[1]);
                } elseif (is_array($userFilterValue) && count($userFilterValue) > 0) {
                    $filter->in($property, $userFilterValue);
                } else if ($userFilterValue) {
                    $filter->eq($property, $userFilterValue);
                }
            }
        }

        return $filter;
    }

    public function getConfigFilter($sectionId, Filter $filter): ConfigFilter
    {
        $prices = CIBlockPriceTools::GetCatalogPrices($this->iblockId, ['BASE']);
        $this->facet->setPrices($prices);
        $this->facet->setSectionId($sectionId);

        $this->sectionId = $sectionId;

        $res = $this->facet->query($filter->getResult());

        $result = $this->getResultItems();

        $dictionaryID = [];
        $directoryPredict = [];
        $tmpProperty = [];

        while ($rowData = $res->fetch()) {
            $facetId = $rowData["FACET_ID"];

            if (Storage::isPropertyId($facetId)) {
                $PID = Storage::facetIdToPropertyId($facetId);

                if (!array_key_exists($PID, $result))
                    continue;

                $rowData['PID'] = $PID;
                $tmpProperty[] = $rowData;
                $item = $result[$PID];
                $arUserType = CIBlockProperty::GetUserType($item['userType']);

                if ($item["propertyType"] == "S") {
                    $dictionaryID[] = $rowData["VALUE"];
                }

                if ($item["propertyType"] == "E" && $item['USER_TYPE'] == '') {
                    $elementDictionary[] = $rowData['VALUE'];
                }

                if ($item["propertyType"] == "G" && $item['USER_TYPE'] == '') {
                    $sectionDictionary[] = $rowData['VALUE'];
                }

                if ($item['userType'] == 'directory' && isset($arUserType['GetExtendedValue'])) {
                    $tableName = $item['userTypeSettings']['TABLE_NAME'];
                    $directoryPredict[$tableName]['PROPERTY'] = array(
                        'PID' => $item['id'],
                        'USER_TYPE_SETTINGS' => $item['userTypeSettings'],
                        'GetExtendedValue' => $arUserType['GetExtendedValue'],
                    );
                    $directoryPredict[$tableName]['VALUE'][] = $rowData["VALUE"];
                }
            } else {
                $priceId = Storage::facetIdToPriceId($facetId);

                foreach ($prices as $NAME => $arPrice) {
                    if ($arPrice["ID"] == $priceId && isset($result[$NAME])) {
                        $this->fillItemPrices($result[$NAME], $rowData);
                    }
                }
            }
        }

        $this->predictIBElementFetch($elementDictionary);
        $this->predictIBSectionFetch($sectionDictionary);
        $this->processProperties($result, $tmpProperty, $dictionaryID, $directoryPredict);

        $configFilter = new ConfigFilter();
        foreach ($result as $key => $value) {
            if (isset($value['values']['min']) || isset($value['values']['max'])) {
                $filterRange = new ConfigFilterRange();
                $filterRange->setIblockId($value['iblockId']);
                $filterRange->setCode($value['code']);
                $filterRange->setName($value['name']);
                $filterRange->setDisplayType('R');
                $filterRange->setPropertyType($value['propertyType']);
                $filterRange->setHint(htmlspecialchars_decode($value['hint']));
                $filterRange->setMin((float)$value['values']['min']);
                $filterRange->setMax((float)$value['values']['max']);
                $configFilter->addFilterItem($filterRange);
            } else {
                $filterList = new ConfigFilterList();
                $filterList->setIblockId($value['iblockId']);
                $filterList->setCode($value['code']);
                $filterList->setName($value['name']);
                $filterList->setHint(htmlspecialchars_decode($value['hint']));
                $filterList->setPropertyType($value['propertyType']);
                $filterList->setDisplayType($value['displayType']);
                $filterList->setValues(array_values($value['values']));
                $configFilter->addFilterItem($filterList);
            }
        }

        return $configFilter;
    }

    public function predictHlFetch($userType, $valueIDs)
    {
        $values = call_user_func_array(
            $userType['GetExtendedValue'],
            array(
                $userType,
                array("VALUE" => $valueIDs),
            )
        );

        foreach ($values as $key => $value) {
            $this->cache[$userType['PID']][$key] = $value;
        }
    }

    public function processProperties(array &$resultItem, array $elements, array $dictionaryID, array $directoryPredict = [])
    {
        $lookupDictionary = [];
        if (!empty($dictionaryID)) {
            $lookupDictionary = $this->facet->getDictionary()->getStringByIds($dictionaryID);
        }

        if (!empty($directoryPredict)) {
            foreach ($directoryPredict as $directory) {
                if (empty($directory['VALUE']) || !is_array($directory['VALUE']))
                    continue;
                $values = [];
                foreach ($directory['VALUE'] as $item) {
                    if (isset($lookupDictionary[$item]))
                        $values[] = $lookupDictionary[$item];
                }

                if (!empty($values))
                    $this->predictHlFetch($directory['PROPERTY'], $values);
                unset($values);
            }
            unset($directory);
        }

        foreach ($elements as $row) {
            $PID = $row['PID'];

            if ($resultItem[$PID]["propertyType"] == "N") {
                $this->fillItemValues($resultItem[$PID], $row["MIN_VALUE_NUM"]);
                $this->fillItemValues($resultItem[$PID], $row["MAX_VALUE_NUM"]);
            } elseif ($resultItem[$PID]["displayType"] == "U") {
                $this->fillItemValues($resultItem[$PID], FormatDate("Y-m-d", $row["MIN_VALUE_NUM"]));
                $this->fillItemValues($resultItem[$PID], FormatDate("Y-m-d", $row["MAX_VALUE_NUM"]));
            } elseif ($resultItem[$PID]["displayType"] == "S") {
                $addedKey = $this->fillItemValues($resultItem[$PID], $lookupDictionary[$row["VALUE"]], true);

                if ($addedKey <> '') {
                    $resultItem[$PID]["values"][$addedKey]["facetValue"] = $row["VALUE"];
                    $resultItem[$PID]["values"][$addedKey]["count"] = $row["ELEMENT_COUNT"];
                }

                if ($resultItem[$PID]["values"][$addedKey]["value"] == '') {
                    unset($resultItem[$PID]["values"][$addedKey]);
                }
            } else {
                $addedKey = $this->fillItemValues($resultItem[$PID], $lookupDictionary[$row["VALUE"]], true);
                if (!$addedKey) {
                    $addedKey = $this->fillItemValues($resultItem[$PID], $row["VALUE"], true);
                }

                if ($addedKey <> '') {
                    $resultItem[$PID]["values"][$addedKey]["facetValue"] = $row["VALUE"];
                    $resultItem[$PID]["values"][$addedKey]["count"] = $row["ELEMENT_COUNT"];
                }

                if ($resultItem[$PID]["values"][$addedKey]["value"] == '') {
                    unset($resultItem[$PID]["values"][$addedKey]);
                }
            }


        }

        foreach ($resultItem as &$item) {
            $firstValue = reset($item["values"]);
            if (count($item["values"]) > 1 && isset($firstValue['value'])) {
                uasort($item["values"], static function ($a, $b) {
                    return ($a["value"] > $b["value"]) ? 1 : -1;
                });
            }
        }
    }

    public function fillItemValues(&$resultItem, $arProperty)
    {
        if (is_array($arProperty)) {
            if (isset($arProperty["price"])) {
                return null;
            }
            $key = $arProperty["value"];
            $PROPERTY_TYPE = $arProperty["propertyType"];
            $PROPERTY_USER_TYPE = $arProperty["userType"];
            $PROPERTY_ID = $arProperty["id"];
        } else {
            $key = $arProperty;
            $PROPERTY_TYPE = $resultItem["propertyType"];
            $PROPERTY_USER_TYPE = $resultItem["userType"];
            $PROPERTY_ID = $resultItem["id"];
            $arProperty = $resultItem;
        }

        if ($PROPERTY_TYPE == "F") {
            return null;
        } elseif ($PROPERTY_TYPE == "N") {
            $convertKey = (float)$key;
            if ($key == '') {
                return null;
            }


            if (!isset($resultItem["values"]["min"])) {
                $resultItem["values"]["min"] = preg_replace("/\\.0+\$/", "", $key);

            } elseif (!isset($resultItem["values"]["max"])) {
                $resultItem["values"]["max"] = preg_replace("/\\.0+\$/", "", $key);
            }

            return null;
        } elseif ($arProperty["DISPLAY_TYPE"] == "U") {
            $date = mb_substr($key, 0, 10);
            if (!$date) {
                return null;
            }
            $timestamp = MakeTimeStamp($date, "YYYY-MM-DD");
            if (!$timestamp) {
                return null;
            }

            if (
                !isset($resultItem["values"]["min"])
                || !array_key_exists("VALUE", $resultItem["values"]["min"])
                || $resultItem["values"]["min"] > $timestamp
            )
                $resultItem["values"]["min"] = $timestamp;

            if (
                !isset($resultItem["values"]["max"])
                || !array_key_exists("VALUE", $resultItem["values"]["max"])
                || $resultItem["values"]["max"] < $timestamp
            )
                $resultItem["values"]["max"] = $timestamp;

            return null;
        } elseif ($PROPERTY_TYPE == "E" && $key <= 0) {
            return null;
        } elseif ($PROPERTY_TYPE == "G" && $key <= 0) {
            return null;
        } elseif ($key == '') {
            return null;
        }

        $arUserType = [];
        if ($PROPERTY_USER_TYPE != "") {
            $arUserType = CIBlockProperty::GetUserType($PROPERTY_USER_TYPE);
        }

        if ($PROPERTY_USER_TYPE === "DateTime") {
            $key = call_user_func_array(
                $arUserType["GetPublicViewHTML"],
                array(
                    $arProperty,
                    array("VALUE" => $key),
                    array("MODE" => "SIMPLE_TEXT", "DATETIME_FORMAT" => "SHORT"),
                )
            );
            $PROPERTY_TYPE = "S";
        }

        $htmlKey = htmlspecialcharsbx($key);
        if (isset($resultItem["values"][$htmlKey])) {
            return $htmlKey;
        }

        $fileId = null;
        $urlId = null;

        switch ($PROPERTY_TYPE) {
            case "L":
                $enum = CIBlockPropertyEnum::GetByID($key);
                if ($enum) {
                    $value = $enum["VALUE"];
                    $sort = $enum["SORT"];
                    $urlId = toLower($enum["XML_ID"]);
                } else {
                    return null;
                }
                break;
            case "E":
                if (!isset($this->cache[$PROPERTY_TYPE][$key])) {
                    $this->predictIBElementFetch(array($key));
                }

                if (!$this->cache[$PROPERTY_TYPE][$key])
                    return null;

                $value = $this->cache[$PROPERTY_TYPE][$key]["NAME"];
                $sort = $this->cache[$PROPERTY_TYPE][$key]["SORT"];
                $urlId = (int)$this->cache[$PROPERTY_TYPE][$key]["ID"];
                break;
            case "G":
                if (!isset($this->cache[$PROPERTY_TYPE][$key])) {
                    $this->predictIBSectionFetch(array($key));
                }

                if (!$this->cache[$PROPERTY_TYPE][$key])
                    return null;

                $value = $this->cache[$PROPERTY_TYPE][$key]['DEPTH_NAME'];
                $sort = $this->cache[$PROPERTY_TYPE][$key]["LEFT_MARGIN"];
                if ($this->cache[$PROPERTY_TYPE][$key]["CODE"])
                    $urlId = toLower($this->cache[$PROPERTY_TYPE][$key]["CODE"]);
                else
                    $urlId = toLower($value);
                break;
            case "U":
                if (!isset($this->cache[$PROPERTY_ID]))
                    $this->cache[$PROPERTY_ID] = [];

                if (!isset($this->cache[$PROPERTY_ID][$key])) {
                    $this->cache[$PROPERTY_ID][$key] = call_user_func_array(
                        $arUserType["GetPublicViewHTML"],
                        array(
                            $arProperty,
                            array("VALUE" => $key),
                            array("MODE" => "SIMPLE_TEXT"),
                        )
                    );
                }

                $value = $this->cache[$PROPERTY_ID][$key];
                $sort = 0;
                $urlId = toLower($value);
                break;
            case "Ux":
                if (!isset($this->cache[$PROPERTY_ID]))
                    $this->cache[$PROPERTY_ID] = [];

                if (!isset($this->cache[$PROPERTY_ID][$key])) {
                    $this->cache[$PROPERTY_ID][$key] = call_user_func_array(
                        $arUserType["GetExtendedValue"],
                        array(
                            $arProperty,
                            array("VALUE" => $key),
                        )
                    );
                }

                if ($this->cache[$PROPERTY_ID][$key]) {
                    $value = $this->cache[$PROPERTY_ID][$key]['VALUE'];
                    $fileId = $this->cache[$PROPERTY_ID][$key]['FILE_ID'];
                    $sort = (isset($this->cache[$PROPERTY_ID][$key]['SORT']) ? $this->cache[$PROPERTY_ID][$key]['SORT'] : 0);
                    $urlId = toLower($this->cache[$PROPERTY_ID][$key]['UF_XML_ID']);
                } else {
                    return null;
                }
                break;
            case "S":
                $value = $key;
                $sort = 0;
                $urlId = toLower($value);

                if (isset($resultItem['userTypeSettings']['TABLE_NAME'])) {
                    $data = $this->getElementByXmlID($resultItem['userTypeSettings']['TABLE_NAME'], $urlId);

                    if (isset($data['UF_NAME'])) {
                        $value = $data['UF_NAME'];
                    }
                } elseif ($resultItem['userType'] == 'UserID') {
                    $rsUser = \CUser::GetByID($value);
                    $arUser = $rsUser->Fetch();

                    if ($arUser) {
                        $value = sprintf('%s %s', $arUser['NAME'], $arUser['LAST_NAME']);
                    }
                }
                break;
            default:
                $value = $key;
                $sort = 0;
                $urlId = toLower($value);
                break;
        }

        $keyCrc = abs(crc32($htmlKey));
        $safeValue = htmlspecialcharsex($value);
        $sort = (int)$sort;
        $resultItem["values"][$htmlKey] = [
            "htmlValue" => "Y",
            "value" => $safeValue,
            'urlId' => $urlId,
        ];

        if ($fileId) {
            $file = CFile::GetFileArray($fileId);
            $resultItem["values"][$htmlKey]['picture'] = $file['SRC'];
        }

        return $htmlKey;
    }

    protected function getElementByXmlID($hlblockName, $xmlId) {
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $hlblockName]
        ])->fetch();

        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityСlass = $entity->getDataClass();

        $rsItems = $entityСlass::getList([
            'filter' => ['=UF_XML_ID' => $xmlId],
        ]);

        if ($arItem = $rsItems->fetch()){
            return $arItem;
        }

        return null;
    }

    public function predictIBElementFetch($id = [])
    {
        if (!is_array($id) || empty($id)) {
            return;
        }

        $linkFilter = array(
            "ID" => $id,
            "ACTIVE" => "Y",
            "ACTIVE_DATE" => "Y",
            "CHECK_PERMISSIONS" => "Y",
        );

        $link = CIBlockElement::GetList([], $linkFilter, false, false, array("ID", "IBLOCK_ID", "NAME", "SORT", "CODE"));
        while ($el = $link->Fetch()) {
            $this->cache['E'][$el['ID']] = $el;
        }
        unset($el);
        unset($link);
    }

    public function getResultItems()
    {
        $items = [];
        foreach ($this->getIBlockItems($this->iblockId) as $PID => $arItem) {
            $items[$PID] = $arItem;
        }

        if ($this->skuIblockId) {
            foreach ($this->getIBlockItems($this->skuIblockId) as $PID => $arItem) {
                $items[$PID] = $arItem;
            }
        }

        foreach ($this->getPriceItems() as $PID => $arItem) {
            $items[$PID] = $arItem;
        }

        return $items;
    }

    public function getIBlockItems($iblockId)
    {
        $items = [];

        foreach (CIBlockSectionPropertyLink::GetArray($iblockId, $this->sectionId) as $PID => $arLink) {
            if ($arLink["SMART_FILTER"] !== "Y")
                continue;

            if ($arLink["ACTIVE"] === "N")
                continue;

            $rsProperty = CIBlockProperty::GetByID($PID);
            $arProperty = $rsProperty->Fetch();

            if ($arProperty) {

                $items[$arProperty["ID"]] = array(
                    "id" => (int)$arProperty["ID"],
                    "iblockId" => (int)$iblockId,
                    "code" => $arProperty["CODE"],
                    "name" => $arProperty["NAME"],
                    "propertyType" => $arProperty["PROPERTY_TYPE"],
                    "displayType" => $arLink["DISPLAY_TYPE"],
                    "userType" => $arProperty["USER_TYPE"],
                    "userTypeSettings" => $arProperty["USER_TYPE_SETTINGS"],
                    "displayExpand" => $arLink["DISPLAY_EXPANDED"],
                    "hint" => htmlspecialchars_decode($arLink["FILTER_HINT"]),
                    "values" => [],
                );

                if (
                    $arProperty["PROPERTY_TYPE"] == "N"
                    || $arLink["DISPLAY_TYPE"] == "U"
                ) {
                    $minID = $this->SAFE_FILTER_NAME . '_' . $arProperty['ID'] . '_MIN';
                    $maxID = $this->SAFE_FILTER_NAME . '_' . $arProperty['ID'] . '_MAX';
                    //$items[$arProperty["ID"]]["values"] = array(
                    //    "min" => array(
                    //        "CONTROL_NAME" => $minID,
                    //    ),
                    //    "max" => array(
                    //        "CONTROL_NAME" => $maxID,
                    //    ),
                    //);
                }
            }
        }
        return $items;
    }

    public function getPriceItems()
    {
        $items = [];
        if (!class_exists(CCatalogGroup::class)) {
            return $items;
        }

        $rsPrice = CCatalogGroup::GetList(
            array('SORT' => 'ASC', 'ID' => 'ASC'),
            array('=NAME' => 'BASE'),
            false,
            false,
            array('ID', 'NAME', 'NAME_LANG', 'CAN_ACCESS', 'CAN_BUY')
        );
        while ($arPrice = $rsPrice->Fetch()) {
            if ($arPrice["CAN_ACCESS"] == "Y" || $arPrice["CAN_BUY"] == "Y") {
                $arPrice["NAME_LANG"] = (string)$arPrice["NAME_LANG"];
                if ($arPrice["NAME_LANG"] === '')
                    $arPrice["NAME_LANG"] = $arPrice["NAME"];

                $items[$arPrice["NAME"]] = [
                    "id" => (int)$arPrice["ID"],
                    "code" => 'CATALOG_PRICE_' . $arPrice["ID"],
                    "name" => $arPrice["NAME_LANG"],
                    "price" => true,
                    "values" => [
                        "min" => null,
                        "max" => null,
                    ],
                ];
            }
        }
        return $items;
    }

    public function predictIBSectionFetch($id = array())
    {
        if (!is_array($id) || empty($id))
        {
            return;
        }

        $arLinkFilter = array (
            "ID" => $id,
            "GLOBAL_ACTIVE" => "Y",
            "CHECK_PERMISSIONS" => "Y",
        );

        $link = CIBlockSection::GetList(array(), $arLinkFilter, false, array("ID","IBLOCK_ID","NAME","LEFT_MARGIN","DEPTH_LEVEL","CODE"));
        while ($sec = $link->Fetch())
        {
            $this->cache['G'][$sec['ID']] = $sec;
            $this->cache['G'][$sec['ID']]['DEPTH_NAME'] = str_repeat(".", $sec["DEPTH_LEVEL"]).$sec["NAME"];
        }
        unset($sec);
        unset($link);
    }

    public function fillItemPrices(&$resultItem, $arElement)
    {
        $priceValue = $arElement["MIN_VALUE_NUM"];
        if (
            !isset($resultItem["values"]["min"])
            || $resultItem["values"]["min"] > $priceValue
        ) {
            $resultItem["values"]["min"] = $priceValue;
        }

        $priceValue = $arElement["MAX_VALUE_NUM"];
        if (
            !isset($resultItem["values"]["max"])
            || $resultItem["values"]["max"] < $priceValue
        ) {
            $resultItem["values"]["max"] = $priceValue;
        }
    }
}
