#!/usr/bin/env bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )

# команда по парсингу позиций объявлений
php $SCRIPT_DIR/artisan parsing:avito-positions
# команда для экспорта данных в таблицу
php $SCRIPT_DIR/artisan export:posting-positions

