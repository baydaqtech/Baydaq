<?php

namespace App\Enums;

enum Permission: string
{
    case ClientsView = 'clients.view';
    case ClientsManage = 'clients.manage';
    case ProjectsView = 'projects.view';
    case ProjectsManage = 'projects.manage';
    case ServicesManage = 'services.manage';
    case InvoicesView = 'invoices.view';
    case InvoicesManage = 'invoices.manage';
    case RequestsManage = 'requests.manage';
    case AdminsManage = 'admins.manage';
    case SettingsManage = 'settings.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
