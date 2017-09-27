<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Models\Task;
use App\Models\Client;
use App\Models\Project;

class TimeTrackerController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $account = $user->account;

        if (! $account->hasFeature(ENTITY_TASK)) {
            return trans('texts.tasks_not_enabled');
        }

        $data = [
            'title' => trans('texts.time_tracker'),
            'tasks' => Task::scope()->with('project', 'client.contacts')->whereNull('invoice_id')->get(),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'projects' => Project::scope()->with('client.contacts')->orderBy('name')->get(),
            'account' => $account,
        ];

        return view('tasks.time_tracker', $data);
    }
}
