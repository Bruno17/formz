<?php
class FormzDataExportProcessor extends modObjectGetListProcessor {
    /* Class in model directory */
    public $classKey = 'fmzFormsData';

    /* Language package to load */
    public $languageTopics = array('formz:default');

    /* Field t sort by and direction */
    public $defaultSortField = 'senton';
    public $defaultSortDirection = 'ASC';

    /* Used to load the correct language error message */
    public $objectType = 'formz.form';

    public function beforeQuery() {
        $this->unsetProperty('limit');
        return true;
    }

    /* Search database from backend module */
    public function prepareQueryBeforeCount(xPDOQuery $c) {
    	$form = $this->getProperty('formId');
    	$startDate = $this->getProperty('startDate');
    	$endDate = $this->getProperty('endDate');

    	if (!empty($form)) {
    		$c->where(array(
	    		'form_id' => $form,
	    	));
    	}

        if (! empty($startDate)) {
            $c->andCondition(array(
                'senton:>' => date('Y-m-d', strtotime($startDate)) . ' 00:00:00'
            ));
        }

        if (! empty($endDate)) {
            $c->andCondition(array(
                'senton:<' => date('Y-m-d', strtotime($endDate)) . ' 23:59:59'
            ));
        }
//        var_dump($form, date('Y-m-d H:i:s', strtotime($startDate)), date('Y-m-d H:i:s', strtotime($endDate))); die;
    	return $c;
    }

    /**
     * Iterate through submitted forms and get related data
     * @param array $list
     * @return array
     */
    public function afterIteration(array $list) {
        $currentIndex = 0;
        $lists = array();
        foreach ($list as $item) {
            $form = $this->modx->getObject('fmzForms', $item['form_id']);
            $formData = unserialize($item['data']);
            $fieldsData = $this->modx->getCollection('fmzFormsDataFields', array('data_id' => $item['id']));

            $lists[] = array();
            $lists[$currentIndex][] = !empty($item['senton']) ? date('d/m/Y H:i:s', strtotime($item['senton'])) : '';
            $lists[$currentIndex][] = !empty($formData['ip_address']) ? $formData['ip_address'] : '';

            $header = array('Sent On', 'IP Address');
            foreach ($fieldsData as $fd) {
                $values = unserialize($fd->value);
                if (is_array($values)) {
                    $values = implode('/', $values);
                }

                array_push($header, $fd->label);
                array_push($lists[$currentIndex], $values);
            }

            $currentIndex++;
        }

        $modRes = $this->modx->newObject('modResource');
        $alias = $modRes->cleanAlias($form->get('name'));
        $now = date('d_m_Y_H_i_s', time());
        $filename = $alias . '_' . $now . '.csv';

        $csv = $this->toCSV($lists, $header, ',', '"', "\r\n");
        $this->download($csv, $filename);
    }

    private function toCSV(array $content, array $header, $delimiter = ',', $enclosure, $lineEnding = null)
    {
        if ($lineEnding === null) {
            $lineEnding = PHP_EOL;
        }

        $csv = $enclosure . implode($enclosure . $delimiter . $enclosure, $header) . $enclosure . $lineEnding;
        foreach ($content as $li) {
            $csv .= $enclosure . implode($enclosure . $delimiter . $enclosure, $li) . $enclosure . $lineEnding;
        }

        return $csv;
    }

    private function download($data, $filename)
    {
        $headers = array();
        $headers[] = 'Pragma: public';
        $headers[] = 'Content-type: application/csv; charset=utf-8';
        $headers[] = 'Content-Disposition: attachment; filename="' . $filename . '";';
        $headers[] = 'Content-Transfer-Encoding: binary';
        $headers[] = 'Content-Length: ' . strlen($data);
        $headers[] = 'Pragma: no-cache';

        $this->setHeaders($headers);

        echo $data;
        exit;
    }

    private function setHeaders(array $headers)
    {
        if (headers_sent()) return false;

        foreach ($headers as $header) {
            header((string) $header);
        }
    }
}

return 'FormzDataExportProcessor';
