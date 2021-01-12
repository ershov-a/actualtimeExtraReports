<?php

$USEDBREPLICATE= 1;
$DBCONNECTION_REQUIRED= 0;

include ("../../../../inc/includes.php");

// Название отчета, которое отображается над таблицей с отчетом
$report= new PluginReportsAutoReport(__('Учтенное время всех специалистов'));

// Фильр по времени выполнения задачи (установки галочки)
$time = new PluginReportsDateIntervalCriteria(
   $report,
   'glpi_tickettasks.date_mod',
   __("Дата выполнения")
);

$time->setStartDate(date("Y-m-d " . "00:00:00", strtotime("-1 week")));
$time->setEndDate(date("Y-m-d " . "23:59:59"));
$report->displayCriteriasForm();

// Добавление столбцов в отчет
/*
*  В первых кавычках (строке) название столбца, в котором данные
*  из запроса к таблице.
*  Во второй строке название столбца, именно так оно будет
*  отображаться в отчете. Почему в таком формате - не выяснял,
*  так было во встроенном отчете плагина actual time.
*  В третьей строке - дополнительные параметры. В основном это
*  css для форматирования текста.
*/

$report->setColumns([
  // Столбец ссылок на сущности, в данном случае специалистов
   new PluginReportsColumnLink(
      'user_id',
      __('Специалист'),
      'User',
      [
         'with_navigate' => true
      ]
   ),
  // Столбец timestamp, отображает время
   new PluginReportsColumnTimestamp(
      'totalDuration',
      __("Итого учтено")
   ),
  // Столбец timestamp, отображает время
   new PluginReportsColumnTimestamp(
      'manualTime',
      __("Вручную")
   ),
  // Столбец timestamp, отображает время
   new PluginReportsColumnTimestamp(
      'timerDuration',
      __("Таймер")
   ),
  // Столбец timestamp, отображает время
   new PluginReportsColumn(
      'timerDurationPercent',
      __("Учтено таймером")." (%)"
   )
]);

$report->delCriteria('glpi_tickets_users.type');

// Группиповка по специалистам
$group = "GROUP BY glpi_tickettasks.users_id_tech";

// Основная часть SQL запроса.
/*
*  WHERE state = 2 в задаче установлена галочка (1 - не установлена).
*  Названия после AS должны совпалать с таковыми в объявлении столбцов.
*  IN (...) возвращает ID всех потомков текущей организации glpiactive_entity.
*  Запрос рекурсивный, без WITH RECURSIVE, т.к. это поддерживает только MySQL8+
*/


if ($_SESSION['glpiactive_entity_recursive'] == 1) {
$query = "
SELECT
   glpi_tickettasks.users_id_tech as user_id,
   concat(glpi_users.realname, ' ', glpi_users.firstname) AS techName,
   sum(glpi_tickettasks.actiontime) AS manualTime,
   concat(round(IFNULL(((sum(actual_actiontime)) / (sum(glpi_tickettasks.actiontime) + sum(actual_actiontime)) * 100 ),0),2),'%') AS timerDurationPercent,
   IFNULL(sum(actual_actiontime),0) AS timerDuration,
   (IFNULL(sum(glpi_tickettasks.actiontime),0) + IFNULL(sum(actual_actiontime),0)) AS totalDuration
FROM glpi_plugin_actualtime_tasks
   RIGHT JOIN glpi_tickettasks ON glpi_tickettasks.id = glpi_plugin_actualtime_tasks.tasks_id
   INNER JOIN glpi_tickets ON glpi_tickets.id = glpi_tickettasks.tickets_id
   INNER JOIN glpi_users ON glpi_users.id = glpi_tickettasks.users_id_tech
WHERE state = 2
AND (glpi_tickets.entities_id
IN (select  id
from    (select * from glpi_entities
        order by entities_id, id) glpi_entities_sorted,
        (select @pv := " . $_SESSION['glpiactive_entity']  . ") initialisation
where   find_in_set(entities_id, @pv)
and     length(@pv := concat(@pv, ',', id)))
OR glpi_tickets.entities_id = " . $_SESSION['glpiactive_entity']  . "
)
" . " ";
} else {
$query = "
SELECT
   glpi_tickettasks.users_id_tech as user_id,
   concat(glpi_users.realname, ' ', glpi_users.firstname) AS techName,
   concat(glpi_users.realname, ' ', glpi_users.firstname) AS techName,
   sum(glpi_tickettasks.actiontime) AS manualTime,
   concat(round(IFNULL(((sum(actual_actiontime)) / (sum(glpi_tickettasks.actiontime) + sum(actual_actiontime)) * 100 ),0),2),'%') AS timerDurationPercent,
   IFNULL(sum(actual_actiontime),0) AS timerDuration,
   (IFNULL(sum(glpi_tickettasks.actiontime),0) + IFNULL(sum(actual_actiontime),0)) AS totalDuration
FROM glpi_plugin_actualtime_tasks
   RIGHT JOIN glpi_tickettasks ON glpi_tickettasks.id = glpi_plugin_actualtime_tasks.tasks_id
   INNER JOIN glpi_tickets ON glpi_tickets.id = glpi_tickettasks.tickets_id
   INNER JOIN glpi_users ON glpi_users.id = glpi_tickettasks.users_id_tech
WHERE state = 2
AND glpi_tickets.entities_id = " . $_SESSION['glpiactive_entity']  . "
" . " ";
}

// Заголовок отчета, который будет в любом случае
$reportTitle = ('Учтенное время специалистов');

// Если выбран временной период, добавляем период в заголовок отчета
if ($time->getStartDate() != 'NULL' && $time->getEndDate() != 'NULL'){
  $reportTitle .= ' за период ' . $time->getStartDate() . ' -> ' . $time->getEndDate();
}


// Устанавливаем заголовок отчета
$report->SetTitle($reportTitle);

// Часть из оригинального отчета actual time
$query .= $report->addSqlCriteriasRestriction();
$query .= $group;

// Костыль, чтобы пользователь с id=0 не отображался в отчете
// (вариант "WHERE glpi_tickettasks.users_id <> 0" не отработал)
$query .= " HAVING (user_id <>0) ";

$query .= " ORDER BY techName ASC ";

// Часть из оригинального отчета actual time
$report->setSqlRequest($query);
$report->execute();
