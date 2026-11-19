import 'dart:typed_data';

import 'receipt_delivery_stub.dart'
    if (dart.library.html) 'receipt_delivery_web.dart' as delivery;

Future<void> deliverPdfReceipt(Uint8List bytes, String fileName) =>
    delivery.deliverPdfReceipt(bytes, fileName);

Future<void> deliverImageReceipt(Uint8List bytes, String fileName) =>
    delivery.deliverImageReceipt(bytes, fileName);
