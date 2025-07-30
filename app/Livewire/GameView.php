<?php

namespace App\Livewire;

use Livewire\Component;

class GameView extends Component
{
    public function render()
    {
        return view("livewire.game-view")->layout("components.layouts.app");
    }
}
