<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <style>
<?php include public_path('css/document.css') ?>
        </style>
    </head>
    <body>
        <table id="header">
            <tr>
                <td>
                    {!! $company['header'] !!}
                </td>
                <td class="logo">
                    {!! $company['logo'] !!}
                </td>
            </tr>
        </table>

        <h1 id="title">{{ $title }}</h1>

        <table id="customer" class="head">
            <tr>
                <td>
                    {!! $customer['customer'] !!}
                </td>
                <td class="idents">
                    <span class="gray">{{ __('documents.account-id') }}</span> {{ $customer['wallet_id'] }}<br>
                    <span class="gray">{{ __('documents.customer-no') }}</span> {{ $customer['id'] }}
                </td>
            </tr>
        </table>

        <table id="content" class="content">
            <tr>
                <th class="align-left">{{ __('documents.date') }}</th>
                <th class="align-left description">{{ __('documents.description') }}</th>
                <th class="price">{{ __('documents.amount') }}</th>
            </tr>
@foreach ($items as $item)
            <tr>
                <td class="align-left">{{ $item['date'] }}</td>
                <td class="align-left">{{ $item['description'] }}</td>
                <td class="price">{{ $item['amount'] }}</td>
            </tr>
@endforeach
@if ($vat)
            <tr class="total subtotal">
                <td colspan="2" class="align-right bold">{{ __('documents.subtotal') }}</td>
                <td class="price bold">{{ $subTotal }}</td>
            </tr>
            <tr class="total vat">
                <td colspan="2" class="align-right bold">{{ __('documents.vat', ['rate' => $vatRate]) }}</td>
                <td class="price bold">{{ $totalVat }}</td>
            </tr>
@endif
            <tr class="total">
                <td colspan="2" class="align-right bold">{{ __('documents.total') }}</td>
                <td class="price bold">{{ $total }}</td>
            </tr>
        </table>

        <div id="footer">{!! $company['footer'] !!}</div>
    </body>
</html>
