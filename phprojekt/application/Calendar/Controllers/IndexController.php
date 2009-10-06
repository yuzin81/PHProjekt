<?php
/**
 * Calendar Module Controller for PHProjekt 6.0
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 2.1 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL 2.1 (See LICENSE file)
 * @version    $Id$
 * @author     Eduardo Polidor <polidor@mayflower.de>
 * @package    PHProjekt
 * @subpackage Calendar
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 */

/**
 * Calendar Module Controller for PHProjekt 6.0
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @version    Release: @package_version@
 * @license    LGPL 2.1 (See LICENSE file)
 * @package    PHProjekt
 * @subpackage Calendar
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @author     Eduardo Polidor <polidor@mayflower.de>
 */
class Calendar_IndexController extends IndexController
{
    /**
     * Returns the list of events where the logged user is involved.
     *
     * The return have:
     *  - The metadata of each field.
     *  - The data of all the rows.
     *  - The number of rows.
     *
     * The function use Phprojekt_ModelInformation_Default::ORDERING_LIST for get and sort the fields.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - integer <b>id</b>     List only this id.
     *  - integer <b>count</b>  Use for SQL LIMIT count.
     *  - integer <b>offset</b> Use for SQL LIMIT offset.
     * </pre>
     *
     * The return is in JSON format.
     *
     * @return void
     */
    public function jsonListAction()
    {
        $count  = (int) $this->getRequest()->getParam('count', null);
        $offset = (int) $this->getRequest()->getParam('start', null);
        $itemId = (int) $this->getRequest()->getParam('id', null);

        if (!empty($itemId)) {
            $where = 'id = ' . (int) $itemId;
        } else {
            $where = 'participant_id = ' . (int) PHprojekt_Auth::getUserId();
        }
        $records = $this->getModelObject()->fetchAll($where, null, $count, $offset);

        // Set dates depending on the times
        foreach ($records as $record) {
            $timeZoneComplement = (int) Phprojekt_User_User::getSetting("timeZone", 'UTC');

            $startDate = strtotime($record->startDate);
            $startTime = strtotime($record->startTime);
            $endDate   = strtotime($record->endDate);
            $endTime   = strtotime($record->endTime);

            // Get the database values without convertions
            $startHour = date("H", $startTime) + ($timeZoneComplement * -1);
            if ($startHour > 24) {
                $startHour = $startHour - 24;
            } else if ($startHour < 0) {
                $startHour = 24 + $startHour;
            }

            $endHour = date("H", $endTime) + ($timeZoneComplement * -1);
            if ($endHour > 24) {
                $endHour = $endHour - 24;
            } else if ($endHour < 0) {
                $endHour = 24 + $endHour;
            }

            // Convert again the values
            $startHour = $startHour + $timeZoneComplement;
            $endHour   = $endHour + $timeZoneComplement;

            // Set the new dates
            $valueStartTime = mktime($startHour, date("i", $startTime), 0, date("m", $startDate), date("d", $startDate),
                date("Y", $startDate));
            $valueEndTime = mktime($endHour, date("i", $endTime), 0, date("m", $endDate), date("d", $endDate),
                date("Y", $endDate));
            $record->startDate = date("Y-m-d", $valueStartTime);
            $record->endDate   = date("Y-m-d", $valueEndTime);
        }

        Phprojekt_Converter_Json::echoConvert($records, Phprojekt_ModelInformation_Default::ORDERING_LIST);
    }

    /**
     * Returns the list of events where the logged user is involved,
     * only for one date.
     *
     * The return have:
     *  - The metadata of each field.
     *  - The data of all the rows.
     *  - The number of rows.
     *
     * The function use Phprojekt_ModelInformation_Default::ORDERING_LIST for get and sort the fields.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - date    <b>date</b>   Date for consult.
     *  - integer <b>count</b>  Use for SQL LIMIT count.
     *  - integer <b>offset</b> Use for SQL LIMIT offset.
     * </pre>
     *
     * The return is in JSON format.
     *
     * @return void
     */
    public function jsonDayListSelfAction()
    {
        $count  = (int) $this->getRequest()->getParam('count', null);
        $offset = (int) $this->getRequest()->getParam('start', null);
        $db     = Phprojekt::getInstance()->getDb();
        $date   = $db->quote(Cleaner::sanitize('date', $this->getRequest()->getParam('date', date("Y-m-d"))));

        $where = sprintf('participant_id = %d AND start_date <= %s AND end_date >= %s',
            (int) PHprojekt_Auth::getUserId(), $date, $date);
        $records = $this->getModelObject()->fetchAll($where, null, $count, $offset);

        Phprojekt_Converter_Json::echoConvert($records, Phprojekt_ModelInformation_Default::ORDERING_FORM);
    }

    /**
     * Returns the list of events where some users are involved,
     * only for one date.
     *
     * The return have:
     *  - The metadata of each field.
     *  - The data of all the rows.
     *  - The number of rows.
     *
     * The function use Phprojekt_ModelInformation_Default::ORDERING_LIST for get and sort the fields.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - date    <b>date</b>   Date for consult.
     *  - users   <b>users</b>  Comma separated ids of the users.
     *  - integer <b>count</b>  Use for SQL LIMIT count.
     *  - integer <b>offset</b> Use for SQL LIMIT offset.
     * </pre>
     *
     * The return is in JSON format.
     *
     * @return void
     */
    public function jsonDayListSelectAction()
    {
        $count   = (int) $this->getRequest()->getParam('count', null);
        $offset  = (int) $this->getRequest()->getParam('start', null);
        $date    = Cleaner::sanitize('date', $this->getRequest()->getParam('date', date("Y-m-d")));
        $usersId = $this->getRequest()->getParam('users', null);

        $records = $this->getModelObject()->getUserSelectionRecords($usersId, $date, $count, $offset);

        Phprojekt_Converter_Json::echoConvert($records, Phprojekt_ModelInformation_Default::ORDERING_FORM);
    }

    /**
     * Returns the list of events where the logged user is involved,
     * for a specific period (like week or month).
     *
     * The return have:
     *  - The metadata of each field.
     *  - The data of all the rows.
     *  - The number of rows.
     *
     * The function use Phprojekt_ModelInformation_Default::ORDERING_LIST for get and sort the fields.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - date    <b>dateStart</b> Start date for filter.
     *  - date    <b>dateEnd</b>   End date for filter.
     *  - integer <b>count</b>     Use for SQL LIMIT count.
     *  - integer <b>offset</b>    Use for SQL LIMIT offset.
     * </pre>
     *
     * The return is in JSON format.
     *
     * @return void
     */
    public function jsonPeriodListAction()
    {
        $count     = (int) $this->getRequest()->getParam('count', null);
        $offset    = (int) $this->getRequest()->getParam('start', null);
        $db        = Phprojekt::getInstance()->getDb();
        $dateStart = $db->quote(Cleaner::sanitize('date', $this->getRequest()->getParam('dateStart', date("Y-m-d"))));
        $dateEnd   = $db->quote(Cleaner::sanitize('date', $this->getRequest()->getParam('dateEnd', date("Y-m-d"))));

        $where     = sprintf('participant_id = %d AND start_date <= %s AND end_date >= %s',
            (int) PHprojekt_Auth::getUserId(), $dateEnd, $dateStart);
        $records = $this->getModelObject()->fetchAll($where, "start_date", $count, $offset);

        Phprojekt_Converter_Json::echoConvert($records, Phprojekt_ModelInformation_Default::ORDERING_FORM);
    }

    /**
     * Saves the current item.
     *
     * If the request parameter "id" is null or 0, the function will add a new item,
     * if the "id" is an existing item, the function will update it.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - integer <b>id</b>                      id of the item to save.
     *  - string  <b>startDate</b>               Start Date of the item or recurring.
     *  - string  <b>rrule</b>                   Recurring rule.
     *  - array   <b>dataParticipant</b>         Array with users id involved in the event.
     *  - boolean <b>multipleEvents</b>          Aply the save for one item or multiple events.
     *  - mixed   <b>all other module fields</b> All the fields values to save.
     * </pre>
     *
     * If there is an error, the save will return a Phprojekt_PublishedException,
     * if not, it returns a string in JSON format with:
     * <pre>
     *  - type    => 'success'.
     *  - message => Success message.
     *  - code    => 0.
     *  - id      => Id of the item.
     * </pre>
     *
     * @throws Phprojekt_PublishedException On error in the action save or wrong id.
     *
     * @return void
     */
    public function jsonSaveAction()
    {
        $message              = Phprojekt::getInstance()->translate(self::ADD_TRUE_TEXT);
        $id                   = (int) $this->getRequest()->getParam('id');
        $startDate            = Cleaner::sanitize('date', $this->getRequest()->getParam('startDate', date("Y-m-d")));
        $endDate              = Cleaner::sanitize('date', $this->getRequest()->getParam('endDate', date("Y-m-d")));
        $startTime            = Cleaner::sanitize('time', $this->getRequest()->getParam('startTime', date("H-i-s")));
        $endTime              = Cleaner::sanitize('time', $this->getRequest()->getParam('endTime', date("H-i-s")));
        $rrule                = (string) $this->getRequest()->getParam('rrule', null);
        $participants         = (array) $this->getRequest()->getParam('dataParticipant');
        $multipleEvents       = Cleaner::sanitize('boolean', $this->getRequest()->getParam('multipleEvents'));
        $multipleParticipants = Cleaner::sanitize('boolean', $this->getRequest()->getParam('multipleParticipants'));
        $modification         = false;

        $this->getRequest()->setParam('endTime', $endTime);
        $this->getRequest()->setParam('startTime', $startTime);

        if (!empty($id)) {
            $message      = Phprojekt::getInstance()->translate(self::EDIT_TRUE_TEXT);
            $modification = true;
        }

        $model   = $this->getModelObject();
        $request = $this->getRequest()->getParams();
        $id      = $model->saveEvent($request, $id, $startDate, $endDate, $rrule, $participants, $multipleEvents,
            $multipleParticipants);

        $return = array('type'    => 'success',
                        'message' => $message,
                        'code'    => 0,
                        'id'      => $id);

        Phprojekt_Converter_Json::echoConvert($return);
    }

    /**
     * Deletes a certain event.
     *
     * If the multipleEvents is true, all the related events will be deleted too.
     * If the multipleParticipants is true, the action delete also the events to the other participants.
     *
     * REQUIRES request parameters:
     * <pre>
     *  - integer <b>id</b>                   id of the event to delete.
     *  - boolean <b>multipleEvents</b>       Deletes one item or multiple events.
     *  - boolean <b>multipleParticipants</b> Deletes for multiple participants or just the logged one.
     * </pre>
     *
     * The return is a string in JSON format with:
     * <pre>
     *  - type    => 'success' or 'error'.
     *  - message => Success or error message.
     *  - code    => 0.
     *  - id      => id of the deleted event.
     * </pre>
     *
     * @throws Phprojekt_PublishedException On missing or wrong id, or on error in the action delete.
     *
     * @return void
     */
    public function jsonDeleteAction()
    {
        $id                   = (int) $this->getRequest()->getParam('id');
        $multipleEvents       = Cleaner::sanitize('boolean', $this->getRequest()->getParam('multipleEvents'));
        $multipleParticipants = Cleaner::sanitize('boolean', $this->getRequest()->getParam('multipleParticipants'));

        if (empty($id)) {
            throw new Phprojekt_PublishedException(self::ID_REQUIRED_TEXT);
        }

        $model = $this->getModelObject()->find($id);

        if ($model instanceof Phprojekt_Model_Interface) {
            $model->deleteEvents($multipleEvents, $multipleParticipants);
            $message = Phprojekt::getInstance()->translate(self::DELETE_TRUE_TEXT);
            $return  = array('type'    => 'success',
                             'message' => $message,
                             'code'    => 0,
                             'id'      => $id);

            Phprojekt_Converter_Json::echoConvert($return);
        } else {
            throw new Phprojekt_PublishedException(self::NOT_FOUND);
        }
    }

    /**
     * Returns the relations for one event.
     *
     * Returns a data array with:
     * <pre>
     *  - participants  => All the participants for one item
     *                     (checks the recurrence and returns all the users involved).
     *  - relatedEvents => All the related events to the current one.
     * </pre>
     *
     * REQUIRES request parameters:
     * <pre>
     *  - integer <b>id</b> id of the event to consult.
     * </pre>
     *
     * The return is in JSON format.
     *
     * @return void
     */
    public function jsonGetRelatedDataAction()
    {
        $id   = (int) $this->getRequest()->getParam('id');
        $data = array('data' => array());

        if ($id > 0) {
            $record = $this->getModelObject()->find($id);
            if (isset($record->id)) {
                $participants  = $record->getAllParticipants();
                $relatedEvents = implode(",", $record->getRelatedEvents());
                $data['data']  = array('participants'  => $participants,
                                       'relatedEvents' => $relatedEvents);
            }
        }

        Phprojekt_Converter_Json::echoConvert($data);
    }

    /**
     * Returns a specific list of users.
     *
     * Returns a list of all the selected users with:
     * <pre>
     *  - id      => id of user.
     *  - display => Display for the user.
     * </pre>
     *
     * REQUIRES request parameters:
     * <pre>
     *  - string <b>users</b> Comma separated ids of the users.
     * </pre>
     *
     * The return is in JSON format.
     *
     * @return void
     */
    public function jsonGetSpecificUsersAction()
    {
        $users = explode(",", $this->getRequest()->getParam('users', null));

        $ids = array();
        foreach ($users as $users) {
            $ids[] = (int) $users;
        }
        if (empty($ids)) {
            $ids[] = (int) PHprojekt_Auth::getUserId();
        }

        $db      = Phprojekt::getInstance()->getDb();
        $where   = sprintf('status = %s AND id IN (%s)', $db->quote('A'), implode(", ", $ids));
        $user    = Phprojekt_Loader::getLibraryClass('Phprojekt_User_User');
        $display = $user->getDisplay();
        $records = $user->fetchAll($where, $display);

        $data = array();
        foreach ($records as $record) {
            $data['data'][] = array('id'      => (int) $record->id,
                                    'display' => $record->applyDisplay($display, $record));
        }

        Phprojekt_Converter_Json::echoConvert($data, Phprojekt_ModelInformation_Default::ORDERING_LIST);
    }

    /**
     * Returns the list of events where the logged user is involved,
     * only for one date.
     *
     * The function use Phprojekt_ModelInformation_Default::ORDERING_LIST for get and sort the fields.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - date    <b>date</b>   Date for consult.
     *  - integer <b>count</b>  Use for SQL LIMIT count.
     *  - integer <b>offset</b> Use for SQL LIMIT offset.
     * </pre>
     *
     * The return is in CSV format.
     *
     * @return void
     */
    public function csvDayListSelfAction()
    {
        $count   = (int) $this->getRequest()->getParam('count', null);
        $offset  = (int) $this->getRequest()->getParam('start', null);
        $db      = Phprojekt::getInstance()->getDb();
        $date    = $db->quote(Cleaner::sanitize('date', $this->getRequest()->getParam('date', date("Y-m-d"))));

        $where = sprintf('participant_id = %d AND start_date <= %s AND end_date >= %s',
            (int) PHprojekt_Auth::getUserId(), $date, $date);
        $records = $this->getModelObject()->fetchAll($where, null, $count, $offset);

        Phprojekt_Converter_Csv::echoConvert($records, Phprojekt_ModelInformation_Default::ORDERING_LIST);
    }

    /**
     * Returns the list of events where some users are involved,
     * only for one date.
     *
     * The function use Phprojekt_ModelInformation_Default::ORDERING_LIST for get and sort the fields.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - date    <b>date</b>   Date for consult.
     *  - users   <b>users</b>  Comma separated ids of the users.
     *  - integer <b>count</b>  Use for SQL LIMIT count.
     *  - integer <b>offset</b> Use for SQL LIMIT offset.
     * </pre>
     *
     * The return is in CSV format.
     *
     * @return void
     */
    public function csvDayListSelectAction()
    {
        $count  = (int) $this->getRequest()->getParam('count', null);
        $offset = (int) $this->getRequest()->getParam('start', null);
        $db     = Phprojekt::getInstance()->getDb();
        $date   = $db->quote(Cleaner::sanitize('date', $this->getRequest()->getParam('date', date("Y-m-d"))));
        $users  = explode(",", $this->getRequest()->getParam('users', null));

        $ids = array();
        foreach ($users as $users) {
            $ids[] = (int) $users;
        }
        if (empty($ids)) {
            $ids[] = (int) PHprojekt_Auth::getUserId();
        }

        $where = sprintf('participant_id IN (%s) AND start_date <= %s AND end_date >= %s',
            implode(", ", $ids), $date, $date);
        $records = $this->getModelObject()->fetchAll($where, null, $count, $offset);

        Phprojekt_Converter_Csv::echoConvert($records, Phprojekt_ModelInformation_Default::ORDERING_FORM);
    }

    /**
     * Returns the list of events where the logged user is involved,
     * for a specific period (like week or month).
     *
     * The function use Phprojekt_ModelInformation_Default::ORDERING_LIST for get and sort the fields.
     *
     * OPTIONAL request parameters:
     * <pre>
     *  - date    <b>dateStart</b> Start date for filter.
     *  - date    <b>dateEnd</b>   End date for filter.
     *  - integer <b>count</b>     Use for SQL LIMIT count.
     *  - integer <b>offset</b>    Use for SQL LIMIT offset.
     * </pre>
     *
     * The return is in CSV format.
     *
     * @return void
     */
    public function csvPeriodListAction()
    {
        $count     = (int) $this->getRequest()->getParam('count', null);
        $offset    = (int) $this->getRequest()->getParam('start', null);
        $db        = Phprojekt::getInstance()->getDb();
        $dateStart = $db->quote(Cleaner::sanitize('date', $this->getRequest()->getParam('dateStart', date("Y-m-d"))));
        $dateEnd   = $db->quote(Cleaner::sanitize('date', $this->getRequest()->getParam('dateEnd', date("Y-m-d"))));

        $where     = sprintf('participant_id = %d AND start_date <= %s AND end_date >= %s',
            (int) PHprojekt_Auth::getUserId(), $dateEnd, $dateStart);
        $records = $this->getModelObject()->fetchAll($where, "start_date", $count, $offset);

        Phprojekt_Converter_Csv::echoConvert($records, Phprojekt_ModelInformation_Default::ORDERING_FORM);
    }
}
