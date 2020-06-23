<?php

Route::post('api/v1/ag-grid/{tableName}', 'Radix\Aggrid\AgGridApiController@fetch');