<?php

namespace Radix\Aggrid;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AgGridApiController extends Controller
{
    public function fetch($tableName, Request $request) {
        $tableName = str_replace('-', '_', $tableName);
        $sql = $this->buildSql($tableName, $request);

        $countSql = $this->countSql($tableName, $request);
        
        $data = \DB::select($sql);
        $totalCount = \DB::select($countSql);
        
        return ['data' => $data, 'count' => $totalCount[0]->cnt];
    }
    
    private function countSql($tableName, $request) {

        $selectSql = $this->createSelectSql($request, true);
        $fromSql = " FROM $tableName ";
        $whereSql = $this->createWhereSql($request);

        $sql = $selectSql . $fromSql . $whereSql;
        return $sql;
    }

    private function buildSql($tableName, $request) {

       $selectSql = $this->createSelectSql($request);
        $fromSql = "FROM $tableName ";
        if ($request->join) {
            $join = json_decode($request->join, true);
            $selectSql = $this->addJoin($tableName,$join,$request);
        }
        $whereSql = $this->createWhereSql($request);
        $limitSql = $this->createLimitSql($request);

        $orderBySql = $this->createOrderBySql($request);
        $groupBySql = $this->createGroupBySql($request);

        if ($request->join) {
            $sql = $selectSql . $whereSql . $groupBySql . $orderBySql . $limitSql;
        } else {
            $sql = $selectSql . $fromSql . $whereSql . $groupBySql . $orderBySql . $limitSql;
        }

        return $sql;
    }
    
    private function addJoin($tableName,$joins,$request) {

        $select = "select $tableName.* ";
        foreach ($joins as $key =>$join) {
            $table2 = $join['table'].$key;
            $secondColumns = $join['select'];

            $on = $join['on'];

            $anotherColumn = explode('=',$on);
            $anotherColumn = $anotherColumn[1];
            $select .= " ,$table2.$secondColumns as $anotherColumn";
        }
        $select .= " FROM $tableName ";

        foreach ($joins as $key=>$join) {
            $table2 = $join['table'];
            $on = $join['on'];
            $select .= " LEFT JOIN $table2 as $table2$key on $on ";
        }

        return $select;

    }

    private function createSelectSql($request, $count = false) {
        $rowGroupCols = $request->rowGroupCols;
        $valueCols = $request->valueCols;
        $groupKeys = $request->groupKeys;

        if ($count) {
            return 'select count(*) as cnt';
        }
        
        if (count($valueCols)) {
            $colsToSelect = [];

            foreach($valueCols as $v) {
                $colsToSelect[] = $v['field'];
            }

            return ' select ' . join(', ', $colsToSelect). ' ';
        }

        return ' select *';
    }

    public function createWhereSql($request) {
        $rowGroupCols = $request->rowGroupCols;
        $groupKeys = $request->groupKeys;
        $filterModel = $request->filterModel;

        $whereParts = [];

        foreach($groupKeys as $key => $value) {
            $colName = $rowGroupCols[$index]->field;
            $whereParts[] = $colName . ' = "' . $key . '"';
        }

        foreach ($filterModel as $key => $value) {
            $item = $filterModel[$key];
            $whereParts[] = $this->createFilterSql($key, $value);
        }

        if (count($whereParts) > 0) {
            return ' where ' . join(" and ", $whereParts);
        } else {
            return '';
        }
    }



    private function createFilterSql($key, $item) {
        switch ($item['filterType']) {
            case 'text':
                if (isset($item['type']) AND $item['type'] == 'domainsFilter') {
                    return $this->createDomainsFilterSql($key,$item['filter']);
                } else {
                    if ($item['filter'] === 'isnull') {
                        return $this->createNullFilterSql($key);
                    } elseif ($item['filter'] === 'isnotnull') {
                        return $this->createNotNullFilterSql($key);
                    } else {
                        return $this->createTextFilterSql($key, $item);
                    }
                }
            case 'number':
                return $this->createNumberFilterSql($key, $item);
            case 'date':
                return $this->createDateFilterSql($key, $item);
            case 'set':
                return $this->createSetFilter($key, $item);
            default:
                logger('unkonwn filter type: ' . $item['filterType']);
        }
    }
    
    public function createDomainsFilterSql($key, $item) {
        $domains = array_map('trim', explode(',', $item));
        return $key .' in ('."'" . implode ( "', '", $domains ) . "'".')';
    }
    
    public function createNullFilterSql($key) {
        return $key . ' is NULL';
    }

    public function createNotNullFilterSql($key) {
        return $key . ' is NOT NULL';
    }

    private function createSetFilter($key, $item) {
        return $key .' in ('."'" . implode ( "', '", $item['values'] ) . "'".')';
    }
    
    private function createDateFilterSql($key, $item) {
        switch ($item['type']) {
            case 'equals':
                return $key . ' = "' . $item['dateFrom'] . '"';
            case 'notEqual':
                return $key . ' != "' . $item['dateFrom'] . '"';
            case 'inRange':
                $toDate= $item['dateTo'];
                $fromDate = $item['dateFrom'];
                return " ( $key >= Date('$fromDate') AND $key <= Date('$toDate') ) ";
                break;
            default:
                logger('unknown text filter type: ' . $item['dateFrom']);
                return 'true';
        }
    }

    private function createTextFilterSql($key, $item) {
        switch ($item['type']) {
            case 'equals':
                return $key . ' = "' . $item['filter'] . '"';
            case 'notEqual':
                return $key . ' != "' . $item['filter'] . '"';
            case 'contains':
                return $key . ' like "%' . $item['filter'] . '%"';
            case 'notContains':
                return $key . ' not like "%' . $item['filter'] . '%"';
            case 'startsWith':
                return $key . ' like "' . $item['filter'] . '%"';
            case 'endsWith':
                return $key . ' like "%' . $item['filter'] . '"';
            default:
                logger('unknown text filter type: ' . $item['type']);
                return 'true';
        }
    }

    private function createNumberFilterSql($key, $item) {
        switch (item.type) {
            case 'equals':
                return $key + ' = ' + $item['filter'];
            case 'notEqual':
                return $key + ' != ' + $item['filter'];
            case 'greaterThan':
                return $key + ' > ' + $item['filter'];
            case 'greaterThanOrEqual':
                return $key + ' >= ' + $item['filter'];
            case 'lessThan':
                return $key + ' < ' + $item['filter'];
            case 'lessThanOrEqual':
                return $key + ' <= ' + $item['filter'];
            case 'inRange':
                return '(' + $key + ' >= ' + $item['filter'] + ' and ' + $key + ' <= ' + $item['filterTo'] + ')';
            default:
                logger('unknown number filter type: ' + $item['type']);
                return 'true';
        }
    }

    private function isDoingGrouping($rowGroupCols, $groupKeys) {
        // we are not doing grouping if at the lowest level. we are at the lowest level
        // if we are grouping by more columns than we have keys for (that means the user
        // has not expanded a lowest level group, OR we are not grouping at all).
        return count($rowGroupCols) > count($groupKeys);
    }

    private function createLimitSql($request) {
        $startRow = $request->startRow;
        $endRow = $request->endRow;
        $pageSize = $endRow - $startRow;
        return ' limit ' . ($pageSize + 1) . ' offset ' . $startRow;
    }

    private function createOrderBySql($request) {
        $rowGroupCols = $request->rowGroupCols;
        $groupKeys = $request->groupKeys;
        $sortModel = $request->sortModel;

        $grouping = $this->isDoingGrouping($rowGroupCols, $groupKeys);

        $sortParts = [];
        if ($sortModel) {
            foreach($sortModel as $key=>$item) {
                $sortParts[] = $item['colId'] . ' ' . $item['sort'];
            }
        }
        
        if (count($sortParts) > 0) {
            return ' order by ' . join(', ', $sortParts);
        } else {
            return '';
        }
    }

    private function createGroupBySql($request) {
        $rowGroupCols = $request->rowGroupCols;
        $groupKeys = $request->groupKeys;

        if ($this->isDoingGrouping($rowGroupCols, $groupKeys)) {
            $colsToGroupBy = [];

            $rowGroupCol = $rowGroupCols[count($groupKeys)];
            $colsToGroupBy[] = $rowGroupCol['field'];

            return ' group by ' + join(', ',$colsToGroupBy);
        } else {
            // select all columns
            return '';
        }
    }
}
