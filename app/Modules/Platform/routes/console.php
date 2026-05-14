<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:auto-release-stale-tickets')->everyFiveMinutes()->withoutOverlapping();
