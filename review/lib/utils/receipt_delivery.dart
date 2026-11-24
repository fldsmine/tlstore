import 'dart:typed_data';

import 'receipt_delivery_stub.dart'
    if (dart.library.html) 'receipt_delivery_web.dart'
    as delivery;
import 'receipt_delivery_result.dart';

export 'receipt_delivery_result.dart';

Future<void> deliverPdfReceipt(Uint8List bytes, String fileName) =>
    delivery.deliverPdfReceipt(bytes, fileName);

Future<ImageReceiptDeliveryResult> deliverImageReceipt(
  Uint8List bytes,
  String fileName,
) => delivery.deliverImageReceipt(bytes, fileName);
