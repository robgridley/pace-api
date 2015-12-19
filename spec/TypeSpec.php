<?php

namespace spec\Pace;

use Prophecy\Argument;
use PhpSpec\ObjectBehavior;

class TypeSpec extends ObjectBehavior
{
    function it_should_allow_single_words()
    {
        $this->beConstructedWith('Job');
        $this->name()->shouldReturn('Job');
    }

    function it_should_allow_multiple_words()
    {
        $this->beConstructedWith('ChangeOrderLine');
        $this->name()->shouldReturn('ChangeOrderLine');
    }

    function it_should_allow_all_caps()
    {
        $this->beConstructedWith('CSR');
        $this->name()->shouldReturn('CSR');
    }

    function it_should_not_allow_camel_case()
    {
        $this->beConstructedWith('jobPart');
        $this->shouldThrow('\InvalidArgumentException')->duringInstantiation();
    }

    function it_can_be_constructed_from_property_names()
    {
        $this->beConstructedThrough('fromPropertyName', ['jobPart']);
        $this->name()->shouldReturn('JobPart');
    }

    function it_can_be_constructed_from_irregular_property_names()
    {
        $this->beConstructedThrough('fromPropertyName', ['crmUser']);
        $this->name()->shouldReturn('CRMUser');
    }

    function it_converts_type_names_to_irregular_property_names()
    {
        $this->beConstructedWith('GLAccount');
        $this->propertyName()->shouldReturn('glAccount');
    }
}
