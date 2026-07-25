<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('azari:backup --verify')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('azari:production-audit')->dailyAt('03:30')->withoutOverlapping();
