<?php
// Raptor CRM Home/Landing Page Controller

class HomeController extends Controller {
    public function index() {
        $data = [
            'title' => 'Raptor — Transform Business Data Into Intelligent Decisions'
        ];
        $this->view('home/index', $data);
    }
}
