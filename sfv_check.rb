#!/usr/bin/env ruby
$-w = true # Enable warnings.

require 'zlib'

sfv_file = ARGV[0]
abort "Usage: #{$PROGRAM_NAME} <sfv_file>" if sfv_file.nil?
abort "Cannot read \"#{sfv_file}\"." unless File.readable?(sfv_file)

# Initialize counters.
pass = fail = miss = 0

File.open(sfv_file).each_line do |line|
  # Skip comments.
  next if line[0] == ';'

  # Remove any trailing whitespace such as Windows line endings.
  line.rstrip!

  filename, _, crc = line.rpartition(' ')
  next if filename.nil? || crc.nil?

  # File is located relative to SFV file.
  file = format('%s/%s', File.dirname(sfv_file), filename)

  print "Checking \"#{filename}\"... "

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
