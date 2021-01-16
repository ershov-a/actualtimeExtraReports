<?php

$USEDBREPLICATE= 1;
$DBCONNECTION_REQUIRED= 0;

include ("../../../../inc/includes.php");

// Название отчета, которое отображается над таблицей с отчетом
$report= new PluginReportsAutoReport(__('Учтенное время по задачам выбранного специалиста'));


// Фильр по времени выполнения задачи (установки галочки)
$time = new PluginReportsDateIntervalCriteria(
   $report,
   'glpi_tickettasks.date_mod',
   __("Дата выполнения")
);

// Фильтр по специалистам
$user = new PluginReportsUserCriteria(
   $report,
   'users_id_tech',
   __("Специалист")
);

$time->setStartDate(date("Y-m-d " . "00:00:00", strtotime("-1 week")));
$time->setEndDate(date("Y-m-d " . "23:59:59"));

// Отображение критериев отбора
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
   // "Простой" столбец, отображает ID заявки (без ссылки)
   new PluginReportsColumn(
      'ticketID',
      __("ID")
   ),
   // "Простой" столбец, отображает имя специалиста (без ссылки)
   new PluginReportsColumn(
      'techName',
     __("Специалист")
   ),
   // Столбец ссылок на сущности, в данном случае заголовок заявки, который является ссылкой на нее
   new PluginReportsColumnLink(
      'ticketIDForLink',
      __('Заявка'),
      'Ticket',
      [
        'with_navigate' => true
      ]
   ),
   // "Простой" столбец, отображает текст задачи (html теги не обрабатывает)
   new PluginReportsColumn(
      'taskText',
      __("Текст задачи")
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
   )
]);

$report->delCriteria('glpi_tickets_users.type');

/*
*  WHERE state = 2  - в задаче установлена галочка (1 - не установлена).
*  Названия после AS должны совпалать с таковыми в объявлении столбцов.
*  IN (...) возвращает ID всех потомков текущей организации glpiactive_entity.
*  Запрос рекурсивный, без WITH RECURSIVE, т.к. это поддерживает только MySQL8+ 
*/

if ($_SESSION['glpiactive_entity_recursive'] == 1) {
$query = "
SELECT
   concat(glpi_users.realname, ' ', glpi_users.firstname) AS techName,
   glpi_tickettasks.id AS ticket_id,
   glpi_tickettasks.tickets_id AS ticketID,
   glpi_tickettasks.tickets_id AS ticketIDForLink,
   glpi_tickettasks.content as taskText,
   glpi_tickettasks.actiontime AS manualTime,
   IFNULL(actual_actiontime,0) AS timerDuration,
   (IFNULL(glpi_tickettasks.actiontime,0) + IFNULL(actual_actiontime,0)) AS totalDuration
FROM glpi_plugin_actualtime_tasks
   RIGHT JOIN glpi_tickettasks ON glpi_tickettasks.id = glpi_plugin_actualtime_tasks.tasks_id
   INNER JOIN glpi_tickets ON glpi_tickets.id = glpi_tickettasks.tickets_id
   INNER JOIN glpi_users ON glpi_users.id = glpi_tickettasks.users_id_tech
WHERE (glpi_tickets.entities_id
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
   concat(glpi_users.realname, ' ', glpi_users.firstname) AS techName,
   glpi_tickettasks.id AS ticket_id,
   glpi_tickettasks.tickets_id AS ticketID,
   glpi_tickettasks.tickets_id AS ticketIDForLink,
   glpi_tickettasks.actiontime AS manualTime,
   IFNULL(actual_actiontime,0) AS timerDuration,
   (IFNULL(glpi_tickettasks.actiontime,0) + IFNULL(actual_actiontime,0)) AS totalDuration
FROM glpi_plugin_actualtime_tasks
   RIGHT JOIN glpi_tickettasks ON glpi_tickettasks.id = glpi_plugin_actualtime_tasks.tasks_id
   INNER JOIN glpi_tickets ON glpi_tickets.id = glpi_tickettasks.tickets_id
   INNER JOIN glpi_users ON glpi_users.id = glpi_tickettasks.users_id_tech
WHERE glpi_tickets.entities_id = " . $_SESSION['glpiactive_entity']  . "
" . " ";
}

// В строке выше конкатенация пробела нужна чтобы AND, добавляемый автоматически
// при выборе дат, добавлялся через проблел

// Заголовок отчета, который будет в любом случае
$reportTitle = ('Учтенное время по задачам');

// Если выбран временной период, добавляем период в заголовок отчета
if ($time->getStartDate() != 'NULL' && $time->getEndDate() != 'NULL'){
  $reportTitle .= ' за период ' . $time->getStartDate() . ' -> ' . $time->getEndDate();
}

// Устанавливаем заголовок отчета
$report->SetTitle($reportTitle);

$query .= $report->addSqlCriteriasRestriction();
$query .= " ORDER BY glpi_plugin_actualtime_tasks.actual_end ASC "; 

//$query .= $report->addSqlCriteriasRestriction();
$report->setSqlRequest($query);
$report->execute();
