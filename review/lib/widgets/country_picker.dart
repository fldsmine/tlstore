import 'package:flutter/material.dart';

import '../services/auth_api.dart';
import 'auth_components.dart';

class CountryPicker extends StatefulWidget {
  const CountryPicker({super.key, required this.countries, required this.selected, required this.onChanged});

  final List<CountryOption> countries;
  final CountryOption selected;
  final ValueChanged<CountryOption> onChanged;

  @override
  State<CountryPicker> createState() => _CountryPickerState();
}

class _CountryPickerState extends State<CountryPicker> {
  final _search = TextEditingController();
  String _query = '';
  static const _maxVisible = 5;

  List<CountryOption> get _filtered {
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return widget.countries;
    return widget.countries.where((country) => country.name.toLowerCase().contains(query) || country.code.toLowerCase().contains(query) || country.dialCode.contains(query)).toList();
  }

  @override
  void dispose() { _search.dispose(); super.dispose(); }

  void _openPicker() {
    _search.clear();
    setState(() => _query = '');
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final results = _filtered;
          final visible = results.take(_maxVisible).toList();
          return Container(
            height: MediaQuery.of(context).size.height * .75,
            decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(25))),
            child: SafeArea(
              child: Column(children: [
                Container(margin: const EdgeInsets.only(top: 10, bottom: 15), width: 45, height: 5, decoration: BoxDecoration(color: Colors.black12, borderRadius: BorderRadius.circular(10))),
                const Text('Select country', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: ink)),
                const SizedBox(height: 18),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: TextField(
                    controller: _search,
                    autofocus: true,
                    onChanged: (value) { setState(() => _query = value); setSheetState(() {}); },
                    decoration: InputDecoration(
                      hintText: 'Search country', prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: _query.isEmpty ? null : IconButton(icon: const Icon(Icons.clear), onPressed: () { _search.clear(); setState(() => _query = ''); setSheetState(() {}); }),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Expanded(
                  child: results.isEmpty
                      ? const Center(child: Text('No country found', style: TextStyle(color: mutedInk)))
                      : ListView(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          children: [
                            ...visible.map((country) {
                              final selected = country.code == widget.selected.code;
                              return ListTile(
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                tileColor: selected ? softGreen : null,
                                leading: Text(country.flag, style: const TextStyle(fontSize: 25)),
                                title: Text(country.name, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                                trailing: Row(mainAxisSize: MainAxisSize.min, children: [Text(country.dialCode.isEmpty ? country.code : country.dialCode, style: const TextStyle(color: mutedInk, fontWeight: FontWeight.w700)), if (selected) const Padding(padding: EdgeInsets.only(left: 9), child: Icon(Icons.check_circle, color: brandGreen, size: 19))]),
                                onTap: () { widget.onChanged(country); Navigator.pop(sheetContext); },
                              );
                            }),
                            if (results.length > visible.length) Padding(padding: const EdgeInsets.symmetric(vertical: 15), child: Center(child: Text('and ${results.length - visible.length} more ${results.length - visible.length == 1 ? 'country' : 'countries'}', style: const TextStyle(color: mutedInk, fontSize: 13, fontWeight: FontWeight.w600)))),
                          ],
                        ),
                ),
              ]),
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) => InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: _openPicker,
        child: Container(
          height: 54,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE3E8E2))),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Text(widget.selected.flag, style: const TextStyle(fontSize: 21)),
            const SizedBox(width: 7),
            Text(widget.selected.dialCode.isEmpty ? widget.selected.code : widget.selected.dialCode, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
            const SizedBox(width: 5),
            const Icon(Icons.keyboard_arrow_down, size: 20, color: mutedInk),
          ]),
        ),
      );
}
