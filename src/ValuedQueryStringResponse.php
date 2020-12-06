<?php declare(strict_types=1);

namespace DCarbone\PHPConsulAPI;

/*
   Copyright 2016-2020 Daniel Carbone (daniel.p.carbone@gmail.com)

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

/**
 * Class ValuedQueryStringResponse
 * @package DCarbone\PHPConsulAPI
 */
class ValuedQueryStringResponse extends AbstractValuedWriteResponse
{
    use ResponseValueStringTrait;

    /**
     * ValuedQueryStringResponse constructor.
     * @param string $value
     * @param \DCarbone\PHPConsulAPI\QueryMeta|null $qm
     * @param \DCarbone\PHPConsulAPI\Error|null $err
     */
    public function __construct(string $value, ?QueryMeta $qm, ?Error $err)
    {
        $this->Value = $value;
        parent::__construct($qm, $err);
    }
}