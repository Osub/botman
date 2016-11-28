<?php

namespace Mpociot\SlackBot\Tests;

use Mockery as m;
use Mpociot\SlackBot\Button;
use Mpociot\SlackBot\Drivers\SlackDriver;
use Mpociot\SlackBot\Http\Curl;
use Mpociot\SlackBot\Message;
use Mpociot\SlackBot\Question;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;

class SlackDriverTest extends PHPUnit_Framework_TestCase
{

    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = m::mock(\Illuminate\Http\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }
        return new SlackDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'event' => [
                'text' => 'bar',
            ],
        ]);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'bar',
            ],
        ]);
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertSame('Hi Julia', $driver->getMessages()[0]->getMessage());
    }

    /** @test */
    public function it_does_not_return_messages_for_bots()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'bot_id' => 'foo',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertSame('', $driver->getMessages()[0]->getMessage());
    }

    /** @test */
    public function it_detects_bots()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertFalse($driver->isBot());

        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'bot_id' => 'foo',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertTrue($driver->isBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
            ],
        ]);
        $this->assertSame('U0X12345', $driver->getMessages()[0]->getUser());
    }

    /** @test */
    public function it_returns_the_channel_id()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
            ],
        ]);
        $this->assertSame('general', $driver->getMessages()[0]->getChannel());
    }

    /** @test */
    public function it_returns_the_message_for_conversation_answers()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response'
            ],
        ]);
        $this->assertSame('response', $driver->getConversationAnswer()->getText());
    }

    /** @test */
    public function it_detects_users_from_interactive_messages()
    {
        $request = new \Illuminate\Http\Request();
        $request->replace([
            'payload' => file_get_contents(__DIR__.'/Fixtures/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $this->assertSame('U045VRZFT', $driver->getMessages()[0]->getUser());
    }

    /** @test */
    public function it_detects_bots_from_interactive_messages()
    {
        $request = new \Illuminate\Http\Request();
        $request->replace([
            'payload' => file_get_contents(__DIR__.'/Fixtures/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $this->assertFalse($driver->isBot());
    }

    /** @test */
    public function it_detects_channels_from_interactive_messages()
    {
        $request = new \Illuminate\Http\Request();
        $request->replace([
            'payload' => file_get_contents(__DIR__.'/Fixtures/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $this->assertSame('C065W1189', $driver->getMessages()[0]->getChannel());
    }

    /** @test */
    public function it_returns_answer_from_interactive_messages()
    {
        $request = new \Illuminate\Http\Request();
        $request->replace([
            'payload' => file_get_contents(__DIR__.'/Fixtures/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $this->assertSame('yes', $driver->getConversationAnswer()->getValue());
    }

    /** @test */
    public function it_can_reply_string_messages()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response'
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'token' => 'Foo',
                'channel' => 'general',
                'text' => 'Test'
            ]);

        $request = m::mock(\Illuminate\Http\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack_token' => 'Foo'
        ], $html);

        $message = new Message('', '', 'general');
        $driver->reply('Test', $message);
    }

    /** @test */
    public function it_can_reply_questions()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response'
            ],
        ];

        $question = Question::create('How are you doing?')
            ->addButton(Button::create('Great'))
            ->addButton(Button::create('Good'));

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'token' => 'Foo',
                'channel' => 'general',
                'text' => '',
                'attachments' => json_encode([$question]),
            ]);

        $request = m::mock(\Illuminate\Http\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack_token' => 'Foo'
        ], $html);

        $message = new Message('', '', 'general');
        $driver->reply($question, $message);
    }

    /** @test */
    public function it_can_reply_with_additional_parameters()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response'
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'token' => 'Foo',
                'channel' => 'general',
                'text' => 'Test',
                'username' => 'ReplyBot',
                'icon_emoji' => ':dash:',
                'attachments' => json_encode([[
                    'image_url' => 'imageurl',
                ]])
            ]);

        $request = m::mock(\Illuminate\Http\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack_token' => 'Foo'
        ], $html);


        $message = new Message('response', '', 'general');
        $driver->reply('Test', $message, [
            'username' => 'ReplyBot',
            'icon_emoji' => ':dash:',
            'attachments' => json_encode([[
                'image_url' => 'imageurl',
            ]])
        ]);
    }
}