<?php

class ApiType
{
    const PROD = 'https://api.monetha.io/mth-gateway/';
    const TEST = 'https://api-sandbox.monetha.io/mth-gateway/';
}

class EventType
{
    const CANCELLED = 'order.canceled';
    const FINALIZED = 'order.finalized';
    const PING = 'order.ping';
}

class Resource
{
    const ORDER = 'order';
}
