<?php

namespace App\Services\Slack;

/**
 * Fluent builder for constructing Slack messages.
 *
 * Supports both legacy attachments and Block Kit blocks.
 *
 * Usage:
 *   $message = SlackMessageBuilder::create()
 *       ->text('Hello!')
 *       ->channel('#general')
 *       ->emoji(':wave:')
 *       ->attachment(['color' => 'good', 'text' => 'Success!'])
 *       ->toArray();
 */
class SlackMessageBuilder
{
    protected ?string $text = null;
    protected ?string $channel = null;
    protected ?string $threadTs = null;
    protected ?string $username = null;
    protected ?string $iconEmoji = null;
    protected ?string $iconUrl = null;
    protected array $attachments = [];
    protected array $blocks = [];
    protected bool $unfurlLinks = true;
    protected bool $unfurlMedia = true;
    protected ?string $parseMode = null;
    protected bool $mrkdwn = true;

    /**
     * Create a new builder instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the main message text.
     */
    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Set the target channel (e.g., '#general', 'C1234567890').
     */
    public function channel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }

    /**
     * Set thread timestamp for reply threading.
     */
    public function threadTs(string $threadTs): self
    {
        $this->threadTs = $threadTs;
        return $this;
    }

    /**
     * Alias for threadTs for more readable code.
     */
    public function inThread(string $threadTs): self
    {
        return $this->threadTs($threadTs);
    }

    /**
     * Set the bot username displayed in Slack.
     */
    public function username(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Set the bot icon emoji (e.g., ':robot:').
     */
    public function emoji(string $emoji): self
    {
        $this->iconEmoji = $emoji;
        $this->iconUrl = null; // emoji takes precedence
        return $this;
    }

    /**
     * Alias for emoji.
     */
    public function iconEmoji(string $emoji): self
    {
        return $this->emoji($emoji);
    }

    /**
     * Set the bot icon URL.
     */
    public function iconUrl(string $url): self
    {
        $this->iconUrl = $url;
        $this->iconEmoji = null; // url takes precedence when set after emoji
        return $this;
    }

    /**
     * Add a single attachment (legacy format).
     */
    public function attachment(array $attachment): self
    {
        $this->attachments[] = $attachment;
        return $this;
    }

    /**
     * Set multiple attachments at once.
     */
    public function attachments(array $attachments): self
    {
        $this->attachments = array_merge($this->attachments, $attachments);
        return $this;
    }

    /**
     * Add a single Block Kit block.
     */
    public function block(array $block): self
    {
        $this->blocks[] = $block;
        return $this;
    }

    /**
     * Set multiple Block Kit blocks at once.
     */
    public function blocks(array $blocks): self
    {
        $this->blocks = array_merge($this->blocks, $blocks);
        return $this;
    }

    /**
     * Add a section block with text.
     */
    public function section(string $text, string $type = 'mrkdwn'): self
    {
        return $this->block([
            'type' => 'section',
            'text' => [
                'type' => $type,
                'text' => $text,
            ],
        ]);
    }

    /**
     * Add a divider block.
     */
    public function divider(): self
    {
        return $this->block(['type' => 'divider']);
    }

    /**
     * Add a header block.
     */
    public function header(string $text): self
    {
        return $this->block([
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => $text,
                'emoji' => true,
            ],
        ]);
    }

    /**
     * Add a context block with elements.
     */
    public function context(array $elements): self
    {
        return $this->block([
            'type' => 'context',
            'elements' => $elements,
        ]);
    }

    /**
     * Add a context block with simple text.
     */
    public function contextText(string $text, string $type = 'mrkdwn'): self
    {
        return $this->context([
            ['type' => $type, 'text' => $text],
        ]);
    }

    /**
     * Add an actions block with buttons/selects.
     */
    public function actions(array $elements): self
    {
        return $this->block([
            'type' => 'actions',
            'elements' => $elements,
        ]);
    }

    /**
     * Add an image block.
     */
    public function image(string $url, string $altText, ?string $title = null): self
    {
        $block = [
            'type' => 'image',
            'image_url' => $url,
            'alt_text' => $altText,
        ];

        if ($title) {
            $block['title'] = [
                'type' => 'plain_text',
                'text' => $title,
            ];
        }

        return $this->block($block);
    }

    /**
     * Control URL unfurling.
     */
    public function unfurlLinks(bool $unfurl = true): self
    {
        $this->unfurlLinks = $unfurl;
        return $this;
    }

    /**
     * Control media unfurling.
     */
    public function unfurlMedia(bool $unfurl = true): self
    {
        $this->unfurlMedia = $unfurl;
        return $this;
    }

    /**
     * Set parse mode ('full', 'none').
     */
    public function parseMode(string $mode): self
    {
        $this->parseMode = $mode;
        return $this;
    }

    /**
     * Enable/disable markdown parsing.
     */
    public function mrkdwn(bool $enabled = true): self
    {
        $this->mrkdwn = $enabled;
        return $this;
    }

    /**
     * Build the message payload array.
     */
    public function toArray(): array
    {
        $payload = [];

        if ($this->text !== null) {
            $payload['text'] = $this->text;
        }

        if ($this->channel !== null) {
            $payload['channel'] = $this->channel;
        }

        if ($this->threadTs !== null) {
            $payload['thread_ts'] = $this->threadTs;
        }

        if ($this->username !== null) {
            $payload['username'] = $this->username;
        }

        if ($this->iconEmoji !== null) {
            $payload['icon_emoji'] = $this->iconEmoji;
        } elseif ($this->iconUrl !== null) {
            $payload['icon_url'] = $this->iconUrl;
        }

        if (!empty($this->attachments)) {
            $payload['attachments'] = $this->attachments;
        }

        if (!empty($this->blocks)) {
            $payload['blocks'] = $this->blocks;
        }

        $payload['unfurl_links'] = $this->unfurlLinks;
        $payload['unfurl_media'] = $this->unfurlMedia;
        $payload['mrkdwn'] = $this->mrkdwn;

        if ($this->parseMode !== null) {
            $payload['parse'] = $this->parseMode;
        }

        return $payload;
    }

    /**
     * Get the channel if set.
     */
    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /**
     * Get the thread timestamp if set.
     */
    public function getThreadTs(): ?string
    {
        return $this->threadTs;
    }

    /**
     * Check if message has blocks.
     */
    public function hasBlocks(): bool
    {
        return !empty($this->blocks);
    }

    /**
     * Check if message has attachments.
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }
}
