#!/usr/bin/env ruby
$-w = true # Enable warnings.

require 'zlib'

sfv_file = ARGV[0]
abort "Usage: #{$PROGRAM_NAME} <sfv_file>" unless sfv_file
abort %(Cannot read "#{sfv_file}".) unless File.readable?(sfv_file)

# Initialize counters.
pass = fail = miss = 0

open(sfv_file).each_line do |line|
  # Skip comments.
  next if line[0] == ';'

  filename, _, crc = line.rstrip.rpartition(' ')
  next unless filename[0] && crc[0]

  # File is located relative to SFV file.
  file = "#{File.dirname(sfv_file)}/#{filename}"

  print %(Checking "#{filename}"... )

  unless File.readable?(file)
    miss += 1
    puts 'MISSING'

    next
  end

  if (hash = format('%08x', Zlib.crc32(File.binread(file)))) == crc.downcase
    pass += 1
    puts 'OK'
  else
    fail += 1
    puts "FAILED (our hash #{hash} does not match #{crc})"
  end
end.close

puts "\nSummary: #{pass} passed, #{fail} failed, #{miss} missing."
