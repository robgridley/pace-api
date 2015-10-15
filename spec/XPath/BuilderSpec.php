<?php

namespace spec\Pace\XPath;

use Carbon\Carbon;
use Prophecy\Argument;
use PhpSpec\ObjectBehavior;

class BuilderSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Pace\XPath\Builder');
    }

    function it_converts_numbers_to_strings()
    {
        $this->filter('@thinking', 92)->getXPathFilter()->shouldReturn('@thinking = 92');
    }

    function it_wraps_strings_in_double_quotes()
    {
        $this->filter('@hashtag', '#edwing')->getXPathFilter()->shouldReturn('@hashtag = "#edwing"');
    }

    function it_converts_booleans_to_strings_wrapped_in_single_quotes()
    {
        $this->filter('@alEastChamps', true)->getXPathFilter()->shouldReturn('@alEastChamps = \'true\'');
    }

    function it_defaults_to_equals()
    {
        $this->filter('@worry', false)->getXPathFilter()->shouldReturn('@worry = \'false\'');
    }

    function it_allows_you_to_specify_a_supported_operator()
    {
        $this->filter('@rbi', '>', 120)->getXPathFilter()->shouldReturn('@rbi > 120');
    }

    function it_throws_an_exception_if_you_specify_an_unsupported_operator()
    {
        $this->shouldThrow('\InvalidArgumentException')->duringFilter('@rbi', '<>', 120);
    }

    function it_supports_the_contains_function()
    {
        $this->contains('@twitter', 'bats')->getXPathFilter()->shouldReturn('contains(@twitter, "bats")');
    }

    function it_supports_the_starts_with_function()
    {
        $this->startsWith('@name', 'Jose')
            ->getXPathFilter()->shouldReturn('starts-with(@name, "Jose")');
    }

    function it_converts_carbon_instances_to_xpath_dates()
    {
        date_default_timezone_set('America/Toronto'); // Prevent potential errors

        $this->filter('@clinchedDivision', Carbon::createFromDate(2015, 9, 30))
            ->getXPathFilter()
            ->shouldReturn('@clinchedDivision = date(2015, 9, 30)');
    }

    function it_defaults_to_and()
    {
        $this->filter('@thinking92', true)
            ->filter('@joeyLovesIt', true)
            ->getXPathFilter()
            ->shouldReturn('@thinking92 = \'true\' and @joeyLovesIt = \'true\'');
    }

    function it_allows_you_to_specify_or()
    {
        $this->filter('@homerun', true)
            ->orFilter('@baseHit', true)
            ->getXPathFilter()
            ->shouldReturn('@homerun = \'true\' or @baseHit = \'true\'');
    }

    function it_uses_closures_to_create_filter_groups()
    {
        $this->filter(function ($xpath) {
                $xpath->filter('@pitcher', 'David Price')->filter('@catcher', 'Dioner Navarro');
            })
            ->orFilter(function ($xpath) {
                $xpath->filter('@pitcher', 'R.A. Dickey')->filter('@catcher', 'Russell Martin');
            })
            ->getXPathFilter()
            ->shouldReturn(
                '(@pitcher = "David Price" and @catcher = "Dioner Navarro") ' .
                'or (@pitcher = "R.A. Dickey" and @catcher = "Russell Martin")'
            );
    }
}
