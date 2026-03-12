<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ScheduledTaskRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class TarefasAgendadasController extends Controller
{
    /**
     * Tarefas permitidas para execução manual pelo Super Admin (signature do comando).
     *
     * @var array<int, string>
     */
    private const ALLOWED_TASKS_TO_RUN = [
        'parcelas:marcar-atrasadas',
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->isSuperAdmin()) {
                abort(403, 'Acesso negado. Apenas Super Admin pode acessar esta área.');
            }
            return $next($request);
        });
    }

    /**
     * Listar execuções de tarefas agendadas (crons).
     */
    public function index(Request $request): View
    {
        $taskNames = ScheduledTaskRun::taskNamesExistentes();

        $query = ScheduledTaskRun::query();

        if ($request->filled('task_name')) {
            $query->where('task_name', $request->task_name);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $runs = $query->orderByDesc('started_at')->paginate(30)->withQueryString();

        $stats = [
            'total' => ScheduledTaskRun::count(),
            'success' => ScheduledTaskRun::where('status', ScheduledTaskRun::STATUS_SUCCESS)->count(),
            'failed' => ScheduledTaskRun::where('status', ScheduledTaskRun::STATUS_FAILED)->count(),
            'running' => ScheduledTaskRun::where('status', ScheduledTaskRun::STATUS_RUNNING)->count(),
        ];

        $allowedTasksToRun = self::ALLOWED_TASKS_TO_RUN;

        return view('super-admin.tarefas-agendadas.index', compact('runs', 'taskNames', 'stats', 'allowedTasksToRun'));
    }

    /**
     * Executar uma tarefa agendada manualmente (apenas tarefas na whitelist).
     */
    public function executar(Request $request): RedirectResponse
    {
        $request->validate([
            'task_name' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_TASKS_TO_RUN)],
        ], [
            'task_name.required' => 'Selecione uma tarefa.',
            'task_name.in' => 'Tarefa não permitida para execução manual.',
        ]);

        $taskName = $request->input('task_name');

        if (ScheduledTaskRun::where('task_name', $taskName)->where('status', ScheduledTaskRun::STATUS_RUNNING)->exists()) {
            return redirect()->route('super-admin.tarefas-agendadas.index')
                ->with('error', 'Esta tarefa já está em execução. Aguarde o término.');
        }

        try {
            $exitCode = Artisan::call($taskName);
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return redirect()->route('super-admin.tarefas-agendadas.index')
                    ->with('error', 'A tarefa foi executada mas retornou erro. '.($output ?: 'Código: '.$exitCode));
            }

            $message = 'Tarefa executada com sucesso.';
            if ($output) {
                $message .= ' '.$output;
            }

            return redirect()->route('super-admin.tarefas-agendadas.index')
                ->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->route('super-admin.tarefas-agendadas.index')
                ->with('error', 'Erro ao executar a tarefa: '.$e->getMessage());
        }
    }
}
