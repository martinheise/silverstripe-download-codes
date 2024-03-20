<?php

namespace Mhe\DownloadCodes\Controller;

use Colymba\BulkManager\BulkAction\Handler;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use Exception;
use Mhe\DownloadCodes\Model\DLCode;
use SilverStripe\Control\HTTPRequest;

class MarkDistributedHandler extends Handler
{
    private static $url_segment = 'markdistributed';

    private static $allowed_actions = array('mark');

    private static $url_handlers = array(
        '' => 'mark',
    );

    protected $label = 'Mark as distributed';

    public function getI18nLabel()
    {
        return _t(self::class . '.ACTION_LABEL', $this->getLabel());
    }

    /**
     * action handler: mark given DLCode records as distributed
     * @param HTTPRequest $request
     * @return HTTPBulkToolsResponse
     */
    public function mark(HTTPRequest $request)
    {
        $response = new HTTPBulkToolsResponse(true, $this->gridField);

        try {
            foreach ($this->getRecords() as $record) {
                if ($record instanceof DLCode) {
                    $response->addSuccessRecord($record);
                    $record->Distributed = true;
                    $record->write();
                }
            }
            $doneCount = count($response->getSuccessRecords() ?? []);
            $message = sprintf(
                'Marked %1$d records.',
                $doneCount
            );
            $response->setMessage($message);
        } catch (Exception $ex) {
            $response->setStatusCode(500);
            $response->setMessage($ex->getMessage());
        }
        return $response;
    }
}
