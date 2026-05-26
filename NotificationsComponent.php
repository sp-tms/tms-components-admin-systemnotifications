<?php

namespace Apps\Tms\Components\System\Notifications;

use Apps\Core\Packages\Adminltetags\Traits\DynamicTable;
use System\Base\BaseComponent;

class NotificationsComponent extends BaseComponent
{
    use DynamicTable;

    protected $notifications;

    public function initialize()
    {
        $this->notifications = $this->basepackages->notifications;
    }

    /**
     * @acl(name=view)
     */
    public function viewAction()
    {
        if (isset($this->getData()['id'])) {
            if ($this->getData()['id'] != 0) {
                $notification = $this->notifications->getById($this->getData()['id']);

                $notification = $this->generateUserInfo($notification['id'], $notification);
                $notification = $this->generateLinkButton($notification['id'], $notification);

                $this->view->notification = $notification;
            }

            $this->view->pick('notifications/view');

            return;
        }

        $conditions =
            [
                'conditions'    =>
                    '-|app_id|equals|' . $this->apps->getAppInfo()['id'] . '&and|account_id|equals|' . $this->access->auth->account()['id'] . '&and|read|equals|0&and|archive|equals|0&',
                'order'         => 'id desc'
            ];

        $replaceColumns =
            function ($dataArr) {
                if ($dataArr && is_array($dataArr) && count($dataArr) > 0) {
                    return $this->replaceColumns($dataArr);
                }

                return $dataArr;
            };

        $this->generateDTContent(
            $this->notifications,
            'system/notifications/view',
            $conditions,
            ['package_row_id', 'read', 'notification_type', 'notification_details', 'notification_title', 'created_by', 'created_at', 'package_name', 'archive'],
            true,
            ['package_row_id', 'read', 'notification_type', 'notification_details', 'notification_title', 'created_by', 'created_at', 'package_name', 'archive'],
            null,
            [
                'package_row_id'        => 'link',
                'notification_title'    => 'notification',
                'notification_type'     => 'type',
                'notification_details'  => 'details',
                'created_by'            => 'User',
                'read'                  => 'actions',
                'archive'               => 'Archived'
            ],
            $replaceColumns,
            'notification_title',
            null,
            false,
            null,
            false,
            false
        );

        $this->view->pick('notifications/list');
    }

    /**
     * @acl(name=remove)
     */
    public function removeAction()
    {
        $this->requestIsPost();

        if (isset($this->postData()['bulk'])) {
            $this->notifications->bulk($this->postData());
        } else {
            $this->notifications->removeNotification($this->postData());
        }

        $this->addResponse(
            $this->notifications->packagesData->responseMessage,
            $this->notifications->packagesData->responseCode
        );
    }

    protected function replaceColumns($dataArr)
    {
        foreach ($dataArr as $dataKey => &$data) {
            $data = $this->generateType($dataKey, $data);
            $data = $this->generateUserInfo($dataKey, $data);
            $data = $this->generateLinkButton($dataKey, $data);
            $data = $this->generateReadButton($dataKey, $data);
            $data = $this->generateArchiveButton($dataKey, $data);
            $data = $this->generateRemoveButton($dataKey, $data);
            $data = $this->generateDetailsButton($dataKey, $data);
        }

        return $dataArr;
    }

    protected function generateType($rowId, $data)
    {
        if ($data['notification_type'] == '0') {
            $data['notification_type'] = '<span class="badge badge-info text-uppercase">INFO</span>';
        } else if ($data['notification_type'] == '1') {
            $data['notification_type'] = '<span class="badge badge-warning text-uppercase">WARNING</span>';
        } else if ($data['notification_type'] == '2') {
            $data['notification_type'] = '<span class="badge badge-danger text-uppercase">ERROR</span>';
        }

        return $data;
    }

    protected function packageLinks()
    {
        $routeLinks = [];

        foreach ($this->modules->packages->packages as $packageKey => $package) {
            if ($package['settings'] && $package['settings'] !== '' && $package['settings'] !== '[]') {
                if (!is_array($package['settings'])) {
                    $package['settings'] = $this->helper->decode($package['settings'], true);
                }
                if (isset($package['settings']['componentRoute'])) {
                    $routeLinks[$package['name']] = $package['settings']['componentRoute'];
                }
            }
        }

        return $routeLinks;
    }

    protected function generateUserInfo($rowId, $data)
    {
        if ($data['created_by'] && $data['created_by'] != '0') {
            $profile = $this->basepackages->profiles->getProfile($data['created_by']);

            if ($profile) {
                $data['created_by'] = $profile['full_name'];
            } else {
                $data['created_by'] = 'System';
            }
        } else {
            $data['created_by'] = 'System';
        }

        return $data;
    }

    protected function generateLinkButton($rowId, $data)
    {
        if ($data['package_row_id']) {
            if (array_key_exists($data['package_name'], $this->packageLinks())) {
                $data['package_row_id'] =
                    '<a id="' . strtolower($this->app['route']) . '-' . strtolower($this->componentName) . '-access-' . $rowId . '" href="' .  $this->links->url($this->packageLinks()[$data['package_name']] . '/q/id/' . $data['package_row_id']) . '" type="button" data-id="' . $data['id'] . '" data-rowid="' . $rowId . '" class="text-white btn btn-primary btn-xs rowAccess text-uppercase contentAjaxLink">
                        <i class="fas fa-fw fa-xs fa-external-link-alt"></i>
                    </a>';
            }
        }

        return $data;
    }

    protected function generateReadButton($rowId, $data)
    {
        if ($data['read'] == 0 && $data['archive'] == 0) {
            $data['read'] =
                '<a id="' . strtolower($this->app['route']) . '-' . strtolower($this->componentName) . '-markread-' . $rowId . '" href="' . $this->links->url('system/notifications/markRead/q/id/' . $data['id']) . '" type="button" data-id="' . $data['id'] . '" data-rowid="' . $rowId . '" class="ml-1 mr-1 text-white btn btn-info btn-xs rowMarkRead text-uppercase">
                    <i class="fas fa-fw fa-xs fa-eye"></i>
                </a>';
        } else {
            $data['read'] = '';
        }

        return $data;
    }

    protected function generateArchiveButton($rowId, $data)
    {
        if ($data['archive'] == 0) {
            $data['read'] .=
                '<a id="' . strtolower($this->app['route']) . '-' . strtolower($this->componentName) . '-markarchive-' . $rowId . '" href="' . $this->links->url('system/notifications/markArchive/q/id/' . $data['id']) . '" type="button" data-id="' . $data['id'] . '" data-rowid="' . $rowId . '" class="ml-1 mr-1 text-white btn btn-primary btn-xs rowArchive text-uppercase">
                    <i class="fas fa-fw fa-xs fa-save"></i>
                </a>';
            $data['archive'] = 'No';
        } else if ($data['archive'] == 1) {
            $data['archive'] = 'Yes';
        }

        return $data;
    }

    protected function generateRemoveButton($rowId, $data)
    {
        $data['read'] .=
            '<a id="' . strtolower($this->app['route']) . '-' . strtolower($this->componentName) . '-remove-__control-' . $rowId . '" href="' . $this->links->url('system/notifications/remove/q/id/' . $data['id']) . '" type="button" data-id="' . $data['id'] . '" data-rowid="' . $rowId . '" class="ml-1 mr-1 text-white btn btn-danger btn-xs rowRemove text-uppercase" data-notificationtextfromcolumn="notification_title">
                <i class="fas fa-fw fa-xs fa-trash"></i>
            </a>';

        return $data;
    }

    protected function generateDetailsButton($rowId, $data)
    {
        if ($data['notification_details'] !== '') {
            $data['notification_details'] =
                '<a id="' . strtolower($this->app['route']) . '-' . strtolower($this->componentName) . '-view-__control-' . $rowId . '" href="' . $this->links->url('system/notifications/q/id/' . $data['id']) . '" type="button" data-id="' . $data['id'] . '" data-rowid="' . $rowId . '" class="ml-1 mr-1 text-white btn btn-primary btn-xs rowView text-uppercase contentAjaxLink">
                    <i class="fas fa-fw fa-xs fa-eye"></i>
                </a>';
        }

        return $data;
    }

    public function fetchNewNotificationsCountAction()
    {
        $this->requestIsPost();

        $this->notifications->fetchNewNotificationsCount();

        $this->addResponse(
            $this->notifications->packagesData->responseMessage,
            $this->notifications->packagesData->responseCode,
            $this->notifications->packagesData->responseData
        );
    }

    public function changeStateAction()
    {
        $this->requestIsPost();

        $this->notifications->changeNotificationState($this->postData());

        $this->addResponse(
            $this->notifications->packagesData->responseMessage,
            $this->notifications->packagesData->responseCode
        );
    }

    public function markReadAction()
    {
        $this->requestIsPost();

        if (isset($this->postData()['bulk'])) {
            $this->notifications->bulk($this->postData());
        } else {
            $this->notifications->markRead($this->postData());
        }

        $this->addResponse(
            $this->notifications->packagesData->responseMessage,
            $this->notifications->packagesData->responseCode
        );
    }

    public function markArchiveAction()
    {
        $this->requestIsPost();

        if (isset($this->postData()['bulk'])) {
            $this->notifications->bulk($this->postData());
        } else {
            $this->notifications->markArchive($this->postData());
        }

        $this->addResponse(
            $this->notifications->packagesData->responseMessage,
            $this->notifications->packagesData->responseCode
        );
    }
}
