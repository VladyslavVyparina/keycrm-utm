<?php

class ControllerStartupKeycrmUtm extends Controller
{
    private const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public function index()
    {
        if (!isset($this->session->data['keycrm_utm']) || !is_array($this->session->data['keycrm_utm'])) {
            $this->session->data['keycrm_utm'] = [];
        }

        foreach (self::UTM_KEYS as $key) {
            if (!empty($this->request->get[$key])) {
                $this->session->data['keycrm_utm'][$key] = (string) $this->request->get[$key];
            }
        }
    }
}
