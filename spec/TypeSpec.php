<?php

namespace spec\Pace;

use Prophecy\Argument;
use PhpSpec\ObjectBehavior;

class TypeSpec extends ObjectBehavior
{
    function it_should_allow_single_words()
    {
        $this->beConstructedWith('Job');
        $this->__toString()->shouldReturn('Job');
    }

    function it_should_allow_multiple_words()
    {
        $this->beConstructedWith('ChangeOrderLine');
        $this->__toString()->shouldReturn('ChangeOrderLine');
    }

    function it_should_allow_all_caps()
    {
        $this->beConstructedWith('CSR');
        $this->__toString()->shouldReturn('CSR');
    }

    function it_should_not_allow_camel_case()
    {
        $this->beConstructedWith('jobPart');
        $this->shouldThrow('\InvalidArgumentException')->duringInstantiation();
    }

    function it_can_be_constructed_with_camel_cased_names()
    {
        $this->beConstructedThrough('decamelize', ['jobPart']);
        $this->__toString()->shouldReturn('JobPart');
    }

    function it_can_be_constructed_with_irregular_camel_cased_names()
    {
        $this->beConstructedThrough('decamelize', ['crmUser']);
        $this->__toString()->shouldReturn('CRMUser');
    }

    function it_converts_type_names_to_irregular_camel_cased_names()
    {
        $this->beConstructedWith('GLAccount');
        $this->camelize()->shouldReturn('glAccount');
    }
}
