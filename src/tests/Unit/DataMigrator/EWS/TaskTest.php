<?php

namespace Tests\Unit\DataMigrator\EWS;

use App\Backends\DAV\Vtodo;
use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\EWS;
use App\DataMigrator\Interface\Folder;
use garethp\ews\API\Type;
use Tests\TestCase;

class TaskTest extends TestCase
{
    /**
     * Test task item processing
     */
    public function testConvertItem(): void
    {
        $source = new Account('ews://test%40domain.tld:test@test');
        $destination = new Account('dav://test%40kolab.org:test@test');
        $engine = new Engine();
        $engine->source = $source;
        $engine->destination = $destination;
        $ews = new EWS($source, $engine);
        $folder = Folder::fromArray(['id' => 'test']);
        $task = new EWS\Task($ews, $folder);

        // FIXME: I haven't found a way to convert xml content into a Type instance
        // therefore we create it "manually", but it would be better to have it in XML.

        $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            . '<body><div>task notes</div></body></html>';

        $item = Type\TaskType::buildFromArray([
            'ItemId' => new Type\ItemIdType(
                'AAMkAGEzOGRlODRiLTBkN2ItNDgwZS04ZDJmLTM5NDEyY2Q0NGQ0OABGAAAAAAC9tlDYSlG2TaxWBr'
                    . 'A1OzWtBwAs2ajhknXlRYN/pbC8JqblAAAAAAEOAAAs2ajhknXlRYN/pbC8JqblAAJnrWkBAAA=',
                'EQAAABYAAAAs2ajhknXlRYN/pbC8JqblAAJnqlKm',
            ),
            'ItemClass' => 'IPM.Task',
            'Subject' => 'Nowe zadanie',
            'LastModifiedTime' => '2024-06-27T13:44:32Z',
            'Sensitivity' => 'Private',
            // TODO: Looks like EWS has Body->IsTruncated property, but is it relevant?
            'Body' => new Type\BodyType($html, 'HTML'),
            'Importance' => 'High',
            'DateTimeCreated' => '2024-06-27T08:58:05Z',
            'ReminderDueBy' => '2024-07-17T07:00:00Z',
            'ReminderIsSet' => true,
            'ReminderNextTime' => '2024-07-17T07:00:00Z',
            'ReminderMinutesBeforeStart' => '0',
            'DueDate' => '2024-06-26T22:00:00Z',
            'IsComplete' => false,
            'IsRecurring' => true,
            'Owner' => 'Alec Machniak',
            'PercentComplete' => '10',
            'Recurrence' => Type\TaskRecurrenceType::buildFromArray([
                'WeeklyRecurrence' => [
                    'Interval' => '1',
                    'DaysOfWeek' => 'Thursday',
                    'FirstDayOfWeek' => 'Sunday',
                ],
                'NoEndRecurrence' => [
                    'StartDate' => '2024-06-27Z',
                ],
            ]),
            'Status' => 'NotStarted',
            'ChangeCount' => '2',
            /*
              <t:Attachments>
                <t:FileAttachment>
                  <t:AttachmentId Id="AAMkAGEzOGRlODRiLTBkN2ItNDgwZS04ZDJmLTM5NDEyY2Q0NGQ0OABGAAAAAAC9tlDYSlG2TaxWBrA1OzWtBwAs2ajhknXlRYN/pbC8JqblAAAAAAESAAAs2ajhknXlRYN/pbC8JqblAAJo4gtPAAABEgAQAANue21Bd/NPlsUns6YmW1Q="/>
                  <t:Name>testdisk.log</t:Name>
                  <t:ContentType>application/octet-stream</t:ContentType>
                  <t:ContentId>299368b3-06e4-42df-959e-d428046f55e6</t:ContentId>
                  <t:Size>249</t:Size>
                  <t:LastModifiedTime>2024-07-16T12:13:58</t:LastModifiedTime>
                  <t:IsInline>false</t:IsInline>
                  <t:IsContactPhoto>false</t:IsContactPhoto>
                </t:FileAttachment>
              </t:Attachments>
              <t:DateTimeReceived>2024-06-27T08:58:05Z</t:DateTimeReceived>
              <t:Size>3041</t:Size>
              <t:Categories>
                <t:String>Kategoria Niebieski</t:String>
              </t:Categories>
              <t:IsSubmitted>false</t:IsSubmitted>
              <t:IsDraft>false</t:IsDraft>
              <t:IsFromMe>false</t:IsFromMe>
              <t:IsResend>false</t:IsResend>
              <t:IsUnmodified>false</t:IsUnmodified>
              <t:DateTimeSent>2024-06-27T08:58:05Z</t:DateTimeSent>
              <t:DisplayTo/>
              <t:HasAttachments>true</t:HasAttachments>
              <t:Culture>en-US</t:Culture>
              <t:EffectiveRights>
                <t:CreateAssociated>false</t:CreateAssociated>
                <t:CreateContents>false</t:CreateContents>
                <t:CreateHierarchy>false</t:CreateHierarchy>
                <t:Delete>true</t:Delete>
                <t:Modify>true</t:Modify>
                <t:Read>true</t:Read>
                <t:ViewPrivateItems>true</t:ViewPrivateItems>
              </t:EffectiveRights>
              <t:LastModifiedName>Alec Machniak</t:LastModifiedName>
              <t:LastModifiedTime>2024-07-16T12:14:38Z</t:LastModifiedTime>
              <t:IsAssociated>false</t:IsAssociated>
              <t:ConversationId Id="AAQkAGEzOGRlODRiLTBkN2ItNDgwZS04ZDJmLTM5NDEyY2Q0NGQ0OAAQANQdjNmPALIE6YAJmOz4Qn4="/>
              <t:Flag>
                <t:FlagStatus>NotFlagged</t:FlagStatus>
              </t:Flag>
              <t:InstanceKey>AQEAAAAAAAESAQAAAmjiC08AAAAA</t:InstanceKey>
              <t:EntityExtractionResult/>
              <t:ChangeCount>1</t:ChangeCount>
              <t:StatusDescription>Not Started</t:StatusDescription>
            */
        ]);

        // Convert the Exchange item into iCalendar
        $ical = $this->invokeMethod($task, 'convertItem', [$item]);

        // Parse the iCalendar output
        $task = new Vtodo();
        $this->invokeMethod($task, 'fromIcal', [$ical]);

        $msId = implode('!', $item->getItemId()->toArray());
        $this->assertSame($msId, $task->custom['X-MS-ID']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $task->uid);
        $this->assertSame('Nowe zadanie', $task->summary);
        $this->assertSame($html, $task->description);
        $this->assertSame('Kolab EWS Data Migrator', $task->prodid);
        $this->assertSame('2', $task->sequence);
        $this->assertSame('9', $task->priority);
        $this->assertSame('PRIVATE', $task->class);
        $this->assertSame(10, $task->percentComplete);
        $this->assertSame('X-NOTSTARTED', $task->status);
        $this->assertSame('2024-06-27T13:44:32+00:00', $task->dtstamp->getDateTime()->format('c'));
        $this->assertSame('2024-06-27T08:58:05+00:00', $task->created->getDateTime()->format('c'));
        $this->assertSame('2024-06-26T22:00:00+00:00', $task->due->getDateTime()->format('c'));
        $this->assertSame('test@kolab.org', $task->organizer['email']);

        $this->assertSame('WEEKLY', $task->rrule['freq']);
        $this->assertSame('1', $task->rrule['interval']);
        $this->assertSame('TH', $task->rrule['byday']);
        $this->assertSame('SU', $task->rrule['wkst']);

        $this->assertCount(1, $task->valarms);
        $this->assertCount(2, $task->valarms[0]);
        $this->assertSame('DISPLAY', $task->valarms[0]['action']);
        $this->assertSame('2024-07-17T07:00:00+00:00', $task->valarms[0]['trigger']->format('c'));
    }

    /**
     * Test processing Recurrence property
     */
    public function testConvertItemRecurrence(): void
    {
        $this->markTestIncomplete();
    }
}
