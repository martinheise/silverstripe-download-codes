<?php

namespace Mhe\DownloadCodes\Controller;

use Colymba\BulkManager\BulkAction\Handler;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use Exception;
use Mhe\DownloadCodes\Model\DLCode;
use SilverStripe\Control\HTTPRequest;

class UnmarkDistributedHandler extends Handler
{
    private static $url_segment = 'unmarkdistributed';

    private static $allowed_actions = array('unmark');

    private static $url_handlers = array(
        '' => 'unmark',
    );

    protected $label = 'Unmark as distributed';

    public function getI18nLabel()
    {
        return _t(self::class . '.ACTION_LABEL', $this->getLabel());
    }

    /**
     * action handler: mark given DLCode records as distributed
     * @param HTTPRequest $request
     * @return HTTPBulkToolsResponse
     */
    public function unmark(HTTPRequest $request)
    {
        $response = new HTTPBulkToolsResponse(true, $this->gridField);

        try {
            foreach ($this->getRecords() as $record) {
                if ($record instanceof DLCode) {
                    $response->addSuccessRecord($record);
                    $record->Distributed = false;
                    $record->write();
                }
            }
            $doneCount = count($response->getSuccessRecords() ?? []);
            $message = sprintf(
                'Unmarked %1$d records.',
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
