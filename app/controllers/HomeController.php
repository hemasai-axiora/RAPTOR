<?php
// Raptor CRM Home/Landing Page Controller

class HomeController extends Controller {
    public function index() {
        $data = [
            'title' => 'Raptor — Transform Business Data Into Intelligent Decisions'
        ];
        $this->view('home/index', $data);
    }

    /**
     * Sentry Verification Endpoint
     * Route: index.php?route=home/test_sentry
     */
    public function testSentry() {
        try {
            $this->functionFailsForSure();
        } catch (\Throwable $exception) {
            $eventId = \Sentry\captureException($exception);
            $this->jsonOk([
                'event_id'  => (string) $eventId,
                'message'   => 'Sentry exception caught and captured successfully.',
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function functionFailsForSure() {
        throw new \RuntimeException('Call to undefined method HomeController::functionFailsForSure() - Sentry verification test exception.');
    }
}
